<?php

require_once 'connection.php';

/**
 * Helps with getting information about the schema, making
 * schema changes, and getting information about the state
 * and configuration of the cluster.
 *
 * @package phpcassa
 * @subpackage sysmanager
 */
class SystemManager {

    /**
     * @param string $server the host and port to connect to, in the
     *        form 'host:port'. Defaults to 'localhost:9160'.
     * @param array $credentials if using authentication or authorization with Cassandra,
     *        a username and password need to be supplied. This should be in the form
     *        array("username" => username, "password" => password)
     * @param int $send_timeout the socket send timeout in milliseconds. Defaults to 15000.
     * @param int $recv_timeout the socket receive timeout in milliseconds. Defaults to 15000.
     */
    public function __construct($server='localhost:9160',
                                $credentials=NULL,
                                $send_timeout=15000,
                                $recv_timeout=15000)
    {
        $this->conn = new ConnectionWrapper(
            NULL, $server, $credentials, True,
            $send_timeout, $recv_timeout);
        $this->client = $this->conn->client;
    }

    /**
     * Closes the underlying Thrift connection.
     */
    public function close() {
        $this->conn->close();
    }

    private function wait_for_agreement() {
        while (true) {
            $versions = $this->client->describe_schema_versions();
            if (count($versions) == 1)
                break;
            usleep(500);
        }
    }

    /**
     * Creates a new keyspace.
     *
     * @param cassandra_KsDef $ksdef
     */
    public function create_keyspace($ksdef) {
        $this->client->system_add_keyspace($ksdef);
        $this->wait_for_agreement();
    }

    /**
     * Modifies a keyspace's properties.
     *
     * Example usage:
     * <code>
     * $sys = SystemManager();
     * $attrs = array("replication_factor" => 2);
     * $sys->alter_keyspace("Keyspace1", $attrs);
     * </code>
     *
     * @param string $keyspace the keyspace to modify
     * @param array $attrs an array that maps attribute
     *        names to values. Valid attribute names include
     *        "strategy_class", "strategy_options", and
     *        "replication_factor".
     *
     */
    public function alter_keyspace($keyspace, $attrs) {
        $ksdef = $this->client->describe_keyspace($keyspace);
        foreach ($attrs as $attr => $value) {
            switch ($attr) {
                case "strategy_class":
                    $ksdef->strategy_class = $value;
                    break;
                case "strategy_options":
                    $ksdef->strategy_options = $value;
                    break;
                case "replication_factor":
                    $ksdef->replication_factor = $value;
                    break;
                default:
                    throw new InvalidArgumentException(
                        "$attr is not a valid keyspace attribute."
                    );
            }
        }

        $this->client->system_update_keyspace($ksdef);
        $this->wait_for_agreement();
    }

    /*
     * Drops a keyspace.
     *
     * @param string $keyspace the keyspace name
     */
    public function drop_keyspace($keyspace) {
        $this->client->system_drop_keyspace($keyspace);
        $this->wait_for_agreement();
    }

    /*
     * Creates a column family.
     *
     * @param cassandra_CfDef $cfdef the CF definition
     */
    public function create_column_family($cfdef) {
        $this->client->set_keyspace($cfdef->keyspace);
        $this->client->system_add_column_family($cfdef);
        $this->wait_for_agreement();
    }

    /*
     * Modifies a column family's attributes.
     *
     * You should build the CfDef by fetching the current
     * one with describe_keyspace() and changing its attributes
     * as necessary.
     *
     * @param cassandra_CfDef $cfdef the CF definition
     */
    public function alter_column_family($cfdef) {
        $this->client->set_keyspace($cfdef->keyspace);
        $this->client->system_update_column_family($cfdef);
        $this->wait_for_agreement();
    }

    /*
     * Drops a column family from a keyspace.
     *
     * @param string $keyspace the keyspace the CF is in
     * @param string $column_family the column family name
     */
    public function drop_column_family($keyspace, $column_family) {
        $this->client->set_keyspace($keyspace);
        $this->client->system_drop_column_family($column_family);
        $this->wait_for_agreement();
    }

    /**
     * Describes the Cassandra cluster. 
     *
     * @return array the node to token mapping
     */
    public function describe_ring($keyspace) {
        return $this->client->describe_ring($keyspace);
    }

    /**
     * Gives the cluster name.
     *
     * @return string the cluster name
     */
    public function describe_cluster_name() {
        return $this->client->describe_cluster_name();
    }

    /**
     * Gives the Thrift API version for the Cassandra instance.
     *
     * Note that this is different than the Cassandra version.
     *
     * @return string the API version
     */
    public function describe_version() {
        return $this->client->describe_version();
    }

    /**
     * Describes what schema version each node currently has.
     * Differences in schema versions indicate a schema conflict.
     *
     * @return array a mapping of schema versions to nodes.
     */
    public function describe_schema_versions() {
        return $this->client->describe_schema_versions();
    }

    /**
     * Describes the cluster's partitioner.
     *
     * @return string the name of the partitioner in use
     */
    public function describe_partitioner() {
        return $this->client->describe_partitioner();
    }

    /**
     * Describes the cluster's snitch.
     *
     * @return string the name of the snitch in use
     */
    public function describe_snitch() {
        return $this->client->describe_snitch();
    }

    /**
     * Returns a description of the keyspace and its column families.
     * This includes all configuration settings for the keyspace and
     * column families.
     *
     * @param string $keyspace the keyspace name
     *
     * @return cassandra_KsDef
     */
    public function describe_keyspace($keyspace) {
        return $this->client->describe_keyspace($keyspace);
    }

    /**
     * Like describe_keyspace(), but for all keyspaces.
     *
     * @return array an array of cassandra_KsDef
     */
    public function describe_keyspaces() {
        return $this->client->describe_keyspace();
    }
}
?>