<?php
/*
 * admin.php - admin plugin controller
 * Copyright (c) 2010  Andr� Kla�en
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

require_once 'app/controllers/authenticated_controller.php';
require_once $this->trails_root.'/models/OCModel.php';
require_once $this->trails_root.'/models/OCEndpointModel.php';
require_once $this->trails_root.'/classes/OCRestClient/SearchClient.php';
require_once $this->trails_root.'/classes/OCRestClient/SeriesClient.php';
require_once $this->trails_root.'/classes/OCRestClient/CaptureAgentAdminClient.php';
require_once $this->trails_root.'/classes/OCRestClient/ServicesClient.php';
require_once $this->trails_root.'/classes/OCRestClient/WorkflowClient.php';


class AdminController extends AuthenticatedController
{
    /**
     * Common code for all actions: set default layout and page title.
     */
    function before_filter(&$action, &$args)
    {
        
        $this->flash = Trails_Flash::instance();

        // set default layout
        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);
        
        // notify on trails action
        $klass = substr(get_called_class(), 0, -10);
        $name = sprintf('oc_admin.performed.%s_%s', $klass, $action);
        NotificationCenter::postNotification($name, $this);

    }

    /**
     * This is the default action of this controller.
     */
    function index_action()
    {
        $this->redirect(PluginEngine::getLink('opencast/admin/config'));
    }

    function config_action()
    {
        PageLayout::setTitle(_("Opencast Administration"));
        Navigation::activateItem('/admin/config/oc-config');


        
        if(($this->info_conf = OCEndpointModel::getBaseServerConf())) {
            $this->info_url = $this->info_conf['service_url'];
            $this->info_user = $this->info_conf['service_user'];
            $this->info_password = $this->info_conf['service_password'];


        }

    }


    function update_action()
    {
        $service_url =  parse_url(Request::get('info_url'));

        if(!array_key_exists('scheme', $service_url)) {
            $this->flash['messages'] = array('error' => _('Es wurde kein g�ltiges URL-Schema angegeben.'));
            OCRestClient::clearConfig($service_url['host']);
            $this->redirect(PluginEngine::getLink('opencast/admin/config'));
        } else {
            $service_host = $service_url['scheme'] .'://' . $service_url['host'] . (isset($service_url['port']) ? ':' . $service_url['port'] : '') ;
            $this->info_url = $service_url['scheme'] .'://' . $service_url['host'] . (isset($service_url['port']) ? ':' . $service_url['port'] : '') .  $service_url['path'];
        

            $this->info_user = Request::get('info_user');
            $this->info_password = Request::get('info_password');
  
            OCRestClient::clearConfig($service_url['host']);
            OCRestClient::setConfig($service_host, $this->info_user, $this->info_password);
             
            OCEndpointModel::setEndpoint($this->info_url, 'services');
            $services_client = ServicesClient::getInstance();


            $comp = $services_client->getRESTComponents();
            if($comp) {
                $services = OCModel::retrieveRESTservices($comp);


                foreach($services as $service_url => $service_type) {

                    $service_url = str_replace("//", "<<", $service_url);
                    $service_comp = explode("/", $service_url);

                    $service_comp[0] = str_replace("<<", "//", $service_comp[0]);
                    $service_url = str_replace("<<", "//", $service_url);

                    if(sizeof($service_comp) >= 2) {
                        if($service_comp)
                        OCEndpointModel::setEndpoint($service_comp[0], $service_type, $service_url);
                    }   
                }


                $this->flash['messages'] = array('success' => sprintf(_("�nderungen wurden erfolgreich �bernommen. Es wurden %s Endpoints f�r die angegeben Opencast Matterhorn Installation gefunden und in der Stud.IP Konfiguration eingetragen"), count($comp)));
            } else {
                $this->flash['messages'] = array('error' => _('Es wurden keine Endpoints f�r die angegeben Opencast Matterhorn Installation gefunden. �berpr�fen Sie bitte die eingebenen Daten.'));
            }

            $this->redirect(PluginEngine::getLink('opencast/admin/config'));
        }
    }
    
    
    function endpoints_action()
    {
        PageLayout::setTitle(_("Opencast Endpoint Verwaltung"));
        Navigation::activateItem('/admin/config/oc-endpoints');
        // hier kann eine Endpoint�berischt angezeigt werden.
        //$services_client = ServicesClient::getInstance();
        $this->endpoints = OCEndpointModel::getEndpoints(); 
    }
    
    function update_endpoints_action()
    {    
        $this->redirect(PluginEngine::getLink('opencast/admin/endpoints'));
    }
    
    
    
    /**
     * brings REST URL in one format before writing in db
     */
    function cleanClientURLs()
    {
        $urls = array('series', 'search', 'scheduling', 'ingest', 'captureadmin'
            , 'upload', 'mediapackage');
            
        foreach($urls as $pre) {
            $var = $pre.'_url';
            $this->$var = rtrim($this->$var,"/");
        }
        
    }

    function resources_action()
    {
        PageLayout::setTitle(_("Opencast Capture Agent Verwaltung"));
        Navigation::activateItem('/admin/config/oc-resources');
        
        $this->resources = OCModel::getOCRessources();
        if(empty($this->resources)) {
            $this->flash['messages'] = array('info' => _('Es wurden keine passenden Ressourcen gefunden.'));

        }

        $caa_client = CaptureAgentAdminClient::getInstance();
        $workflow_client = WorkflowClient::getInstance();
        
        $agents = $caa_client->getCaptureAgents();
        $this->agents = $caa_client->getCaptureAgents();


        foreach ($this->resources as $resource) {
            $assigned_agents = OCModel::getCAforResource($resource['resource_id']);
            if($assigned_agents){
                $existing_agent = false;
                foreach($agents as $key => $agent) {
                    if($agent->name ==  $assigned_agents['capture_agent']) {
                        unset($agents->$key);
                        $existing_agent = true;
                    }
                 }
                if(!$existing_agent){
                    OCModel::removeCAforResource($resource['resource_id'], $assigned_agents['capture_agent']);
                    $this->flash['messages'] = array('info' => sprintf(_("Der Capture Agent %s existiert nicht mehr und wurde entfernt."),$assigned_agents['capture_agent'] ));
                }
            }
        }

        $this->available_agents = $agents;
        $this->definitions = $workflow_client->getDefinitions();

        $this->assigned_cas = OCModel::getAssignedCAS();

    }


    function update_resource_action()
    {

        $this->resources = OCModel::getOCRessources();

        foreach($this->resources as $resource) {
            if(Request::get('action') == 'add'){
                if(($candidate_ca = Request::get($resource['resource_id'])) && $candidate_wf = Request::get('workflow')){
                    $success = OCModel::setCAforResource($resource['resource_id'], $candidate_ca, $candidate_wf);
                }
            }
        }

        if($success) $this->flash['messages'] = array('success' => _("Capture Agents wurden zugewiesen."));

        $this->redirect(PluginEngine::getLink('opencast/admin/resources'));
    }

    function remove_ca_action($resource_id, $capture_agent)
    {
        OCModel::removeCAforResource($resource_id, $capture_agent);
        $this->redirect(PluginEngine::getLink('opencast/admin/resources'));
    }

    // client status
    function client_action()
    {
        $caa_client    = CaptureAgentAdminClient::getInstance();
        $this->agents  = $caa_client->getCaptureAgents();
    }

    function refresh_episodes_action($ticket){
        if(check_ticket($ticket) && $GLOBALS['perm']->have_studip_perm('admin',$this->course_id)) {
            $stmt = DBManager::get()->prepare("SELECT DISTINCT ocs.seminar_id, ocs.series_id FROM oc_seminar_series AS ocs WHERE 1");
            $stmt->execute(array());
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (is_array($courses)) {
                foreach ($courses as $course) {

                    $ocmodel = new OCCourseModel($course['seminar_id']);
                    $ocmodel->getEpisodes(true);
                    unset($ocmodel);
                }
                $this->flash['messages'] = array('success' => _("Die Episodenliste aller Series  wurde aktualisiert."));
            }
        }
        $this->redirect(PluginEngine::getLink('opencast/admin/config/'));
    }
}
?>