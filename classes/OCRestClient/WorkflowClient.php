<?php
require_once "OCRestClient.php";

class WorkflowClient extends OCRestClient
{
    static $me;
    function __construct($config_id = 1) {
        $this->serviceName = 'WorkflowClient';
            if ($config = parent::getConfig('workflow', $config_id)) {
                parent::__construct($config['service_url'],
                                    $config['service_user'],
                                    $config['service_password']);
            } else {
                throw new Exception (_("Die Konfiguration wurde nicht korrekt angegeben"));
            }
    }

    /**
     * getWorkflowInstance - Get a specific workflow instance
     *
     * @param $id The workflow instance identifier
     *
     * @return $result A JSON representation of a workflow instance
     */
    function getWorkflowInstance($id) {
        $service_url = "/instance/" . $id . ".json";
        if($result = $this->getJSON($service_url)){
            return $result->workflow;
        } else return false;
    }

    /**
     * getInstances() - returns all Workflow instances for a given SeriesID
     *
     *  @return array Workflow Instances
     */
    function getInstances($seriesID) {

        $service_url = sprintf( "/instances.json?state=&q=&seriesId=%s&seriesTitle=&creator=&contributor=&fromdate=&todate=&language="
                     . "&license=&title=&subject=&workflowdefinition=&mp=&op=&sort=&startPage=0&count=1000&compact=true", $seriesID);

        if($instances = $this->getJSON($service_url)){
            return $instances;
        } else {
            return false;
        }
    }

    /**
     * getDefinitions() - returns all Workflow definitions
     *
     *  @return array Workflow Instances
     */
    function getDefinitions() {
        $service_url = sprintf( "/definitions.json");

        if ($definitions = $this->getJSON($service_url)) {
            return $definitions;
        } else {
            return false;
        }
    }

    function removeInstanceComplete($id){
        $service_url = sprintf( '/remove/'.$id);
        $options = array(
            CURLOPT_URL => $this->matterhorn_base_url.$service_url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_CUSTOMREQUEST => 'DELETE'
        );

        curl_setopt_array($this->ochandler, $options);
        $response = curl_exec($this->ochandler);
        $http = curl_getinfo($this->ochandler, CURLINFO_HTTP_CODE);

        if (in_array($http,array(204,404))) {
            return true;
        } else {
            return false;
        }
    }

     ####################
     # HELPER FUNCTIONS #
     ####################

    /**
     * getTaggedWorkflowDefinitions() - returns a revised collection of all tagged Workflow definitions
     *
     *  @return array tagged Workflow Instances
     */
    function getTaggedWorkflowDefinitions()
    {
        $wf_defs = self::getDefinitions();

        $tagged_wfs = array();

        foreach ($wf_defs->definitions->definition as $wdef) {
            if (is_array($wdef->tags->tag)) {
                $tagged_wfs[] = array(
                    'id'          => $wdef->id,
                    'title'       => $wdef->title,
                    'description' => $wdef->description,
                    'tags'        => $wdef->tags->tag
                );
            }
        }

        return $tagged_wfs;
    }
}
