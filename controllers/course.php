<?php
/*
 * course.php - course controller
 * Copyright (c) 2010  Andr� Kla�en
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

require_once 'app/controllers/studip_controller.php';
require_once $this->trails_root.'/classes/OCRestClient/SearchClient.php';
require_once $this->trails_root.'/classes/OCRestClient/SeriesClient.php';
require_once $this->trails_root.'/classes/OCRestClient/SchedulerClient.php';
require_once $this->trails_root.'/classes/OCRestClient/UploadClient.php';
require_once $this->trails_root.'/classes/OCRestClient/IngestClient.php';
require_once $this->trails_root.'/classes/OCRestClient/WorkflowClient.php';
require_once $this->trails_root.'/classes/OCRestClient/MediaPackageClient.php';
require_once $this->trails_root.'/models/OCModel.php';

class CourseController extends StudipController
{
    
    /**
     * Sets the page title. Page title always includes the course name.
     *
     * @param mixed $title Title of the page (optional)
     */
    private function set_title($title = '')
    {
        $title_parts   = func_get_args();
        $title_parts[] = $GLOBALS['SessSemName']['header_line'];
        $title_parts =  array_reverse($title_parts);
        $page_title    = implode(' - ', $title_parts);
        PageLayout::setTitle($page_title);
    }
    

    /**
     * Common code for all actions: set default layout and page title.
     */
    function before_filter(&$action, &$args)
    {
        $this->flash = Trails_Flash::instance();
        
        PageLayout::addScript($GLOBALS['ocplugin_path']  . '/vendor/jquery.fileupload.js');
        PageLayout::addScript($GLOBALS['ocplugin_path']  . '/vendor/jquery.simplePagination.js');
        PageLayout::addStylesheet($GLOBALS['ocplugin_path']  . '/vendor/simplePagination.css'); 
        
        //UOS FEEDBACK
        PageLayout::addScript($GLOBALS['ocplugin_path']  . '/vendor/tooltipster/js/jquery.tooltipster.min.js');  
        PageLayout::addStylesheet($GLOBALS['ocplugin_path']  . '/vendor/tooltipster/css/tooltipster-shadow.css'); 
        PageLayout::addStylesheet($GLOBALS['ocplugin_path']  . '/vendor/tooltipster/css/tooltipster.css'); 
        
        
        
        // set default layout
        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);
        $this->pluginpath = $this->dispatcher->trails_root;
        $this->course_id = $_SESSION['SessionSeminar'];
        
        // notify on trails action
        $klass = substr(get_called_class(), 0, -10);
        $name = sprintf('oc_course.performed.%s_%s', $klass, $action);
        NotificationCenter::postNotification($name, $this);
        
    }

    /**
     * This is the default action of this controller.
     */
    function index_action($active_id = 'false', $upload_message = false, $delete_series = false)
    {
       
        $layout = $GLOBALS['template_factory']->open('layouts/base_without_infobox');
        $this->set_layout($layout);

        $this->set_title(_("Opencast Player"));
        if($upload_message == 'true') {
            $this->flash['messages'] = array('success' =>_('Die Datei wurden erfolgreich hochgeladen. Je nach Gr��e der Datei und Auslastung des Opencast Matterhorn-Server kann es einige Zeit in Anspruch nehmen, bis die entsprechende Aufzeichnung in der Liste sichtbar wird.'));
        }
        
        // set layout for index page
        if(!$GLOBALS['perm']->have_studip_perm('dozent', $this->course_id)) {

            $layout = $GLOBALS['template_factory']->open('layouts/base_without_infobox');
            $this->set_layout($layout);
        } else {
            $this->workflow_client = WorkflowClient::getInstance();
            $workflow_ids = OCModel::getWorkflowIDsforCourse($this->course_id);
            $this->states = array();
            $this->series_metadata = OCSeriesModel::getConnectedSeriesDB($this->course_id);
            if(!empty($workflow_ids)){
                foreach($workflow_ids as $workflow_id) {
                    $resp = $this->workflow_client->getWorkflowInstance($workflow_id['workflow_id']);
                    if($resp->state == 'SUCCEEDED') {
                        OCModel::removeWorkflowIDforCourse($workflow_id['workflow_id'], $this->course_id);
                    } else $this->states[$workflow_id['workflow_id']] = $resp;
                }
            }
        }

     
        Navigation::activateItem('course/opencast/overview');
        try {
            $this->search_client = SearchClient::getInstance();

            // lets get all episodes for the connected series
            if (($cseries = OCSeriesModel::getConnectedSeries($this->course_id)) && !isset($this->flash['error'])) {

                $this->episode_ids = array();
                $count = 0;
                $this->search_client = SearchClient::getInstance();
                $positions = OCModel::getCoursePositions($this->course_id);
                $presenter_download = false;
                $presentation_download = false;
                $audio_download = false;

                    foreach($cseries as $serie) {
                        $series = $this->search_client->getEpisodes($serie['identifier']);
                        if(!empty($series)) {
                            foreach($series as $episode) {
                                $visibility = OCModel::getVisibilityForEpisode($this->course_id, $episode->id);
                                if(is_object($episode->mediapackage) && 
                                    (($visibility['visible']!= 'false' && $GLOBALS['perm']->have_studip_perm('autor', $this->course_id)) ||
                                     $GLOBALS['perm']->have_studip_perm('dozent', $this->course_id))){
                                    $count+=1;
                                  
                                    foreach($episode->mediapackage->attachments->attachment as $attachment) {
                                        if($attachment->type === 'presenter/search+preview') $preview = $attachment->url;
                                    }
                                    
                                    foreach($episode->mediapackage->media->track as $track) {
                                        if(($track->type === 'presenter/delivery') && ($track->mimetype === 'video/mp4')){
                                            $url = parse_url($track->url);
                                            if((in_array('high-quality', $track->tags->tag) || in_array('hd-quality', $track->tags->tag)) && $url['scheme'] != 'rtmp') {
                                               $presenter_download = $track->url;
                                            }
                                        }
                                        if(($track->type === 'presentation/delivery') && ($track->mimetype === 'video/mp4')){
                                            $url = parse_url($track->url);
                                            if((in_array('high-quality', $track->tags->tag) || in_array('hd-quality', $track->tags->tag)) && $url['scheme'] != 'rtmp') {
                                               $presentation_download = $track->url;
                                            }
                                        }
                                        if(($track->type === 'presenter/delivery') && (($track->mimetype === 'audio/mp3') || ($track->mimetype === 'audio/mpeg') || ($track->mimetype === 'audio/m4a')))
                                            $audio_download = $track->url;
                                    }
                                    $this->episode_ids[$episode->id] = array('id' => $episode->id,
                                        'title' => $episode->dcTitle,
                                        'start' => $episode->mediapackage->start,
                                        'duration' => $episode->mediapackage->duration,
                                        'description' => $episode->dcDescription,
                                        'author' => $episode->dcCreator,
                                        'preview' => $preview,
                                        'presenter_download' => $presenter_download,
                                        'presentation_download' => $presentation_download,
                                        'audio_download' => $audio_download,
                                        'visibility' => ($visibility['visible'] == 'false') ? false : true
                                    );
                                }
                            }
                        }
                    }
            }
            if($positions) {
                $this->ordered_episode_ids = array();
                foreach($positions as $position) {
                    if(isset($this->episode_ids[$position['episode_id']])){
                         $this->episode_ids[$position['episode_id']]['position'] = $position['position'];
                         $this->ordered_episode_ids[$position['position']] = $this->episode_ids[$position['episode_id']];
                         unset($this->episode_ids[$position['episode_id']]);
                    }
                }
                if(!empty($this->episode_ids)){
                    foreach($this->episode_ids as $episode) {
                        array_unshift($this->ordered_episode_ids, $episode);
                    }
                }
            } elseif(!empty($this->episode_ids)){
                $i = 0;
                foreach($this->episode_ids as $key => $episode){
                    $episode['position'] = $i;
                    $this->ordered_episode_ids[$key] = $episode;
                    OCModel::setCoursePositionForEpisode($key, $i, $this->course_id, 'true');
                    unset($this->episode_ids[$key]);
                    $i++;
                }
            }
            
            if(empty($active_id) || $active_id != "false") {
                $this->active_id = $active_id;
            } else if(isset($this->episode_ids)){
                if($positions) {
                    $x = $this->ordered_episode_ids;
                } else $x = $this->episode_ids;
                $first = array_shift($x);
                $this->active_id = $first['id'];
            }

            if($count > 0) {
                $engage_url =  parse_url($this->search_client->getBaseURL());
                // set true iff theodul is active
                $this->theodul = true;
                if($this->theodul) {
                    $this->embed =  $this->search_client->getBaseURL() ."/engage/theodul/ui/core.html?id=".$this->active_id . "&mode=embed";
                } else {
                    $this->embed =  $this->search_client->getBaseURL() ."/engage/ui/embed.html?id=".$this->active_id;
                }
                // check whether server supports ssl
                $embed_headers = @get_headers("https://". $this->embed);
                if($embed_headers) {
                    $this->embed = "https://". $this->embed;
                } else {
                    $this->embed = "http://". $this->embed;
                }
                $this->engage_player_url = $this->search_client->getBaseURL() ."/engage/ui/watch.html?id=".$this->active_id;
            }
            
            // Upload-Dialog
            $this->date = date('Y-m-d');
            $this->hour = date('H');
            $this->minute = date('i');
            
            //check needed services before showing upload form
            UploadClient::getInstance()->checkService();
            IngestClient::getInstance()->checkService();
            MediaPackageClient::getInstance()->checkService();
            SeriesClient::getInstance()->checkService();
            
            // Config-Dialog
            $this->connectedSeries = OCSeriesModel::getConnectedSeries($this->course_id, true);
            $this->unconnectedSeries = OCSeriesModel::getUnconnectedSeries($this->course_id, true);
            
            // Remove Series
            if($delete_series) {
                $this->flash['delete'] = true;
            }
            
        } catch (Exception $e) {
            $this->flash['error'] = $e->getMessage();
            $this->render_action('_error');
        }
    }
    
    function config_action()
    {
        if (isset($this->flash['messages'])) {
            $this->message = $this->flash['messages'];
        }
        Navigation::activateItem('course/opencast/config');
        $navigation = Navigation::getItem('/course/opencast');
        $navigation->setImage('../../'.$this->dispatcher->trails_root.'/images/oc-logo-black.png');
        $this->course_id = $_SESSION['SessionSeminar'];
        $this->set_title(_("Opencast Konfiguration"));
        
  
        $this->connectedSeries = OCSeriesModel::getConnectedSeries($this->course_id);
        $this->unconnectedSeries = OCSeriesModel::getUnconnectedSeries($this->course_id, true);

    }
    
    function edit_action($course_id)
    {   

        $series = Request::getArray('series');
        
        foreach( $series as $serie) {
            OCSeriesModel::setSeriesforCourse($course_id, $serie);
        }
        $this->flash['messages'] = array('success'=> _("�nderungen wurden erfolgreich �bernommen. Es wurde eine neue Serie f�r den Kurs angelegt."));
        $this->redirect(PluginEngine::getLink('opencast/course/index'));
    }
    
    function remove_series_action($ticket)
    {
         
        $course_id = Request::get('course_id');
        $series_id = Request::get('series_id');
        $delete = Request::get('delete');
        if( $delete && check_ticket($ticket)) {
            
            $scheduled_episodes = OCSeriesModel::getScheduledEpisodes($course_id);

            OCSeriesModel::removeSeriesforCourse($course_id, $series_id);

            /* Uncomment iff you really want to remove this series from the OC Core
            $series_client = SeriesClient::getInstance();
            $series_client->removeSeries($series_id); 
            */
            $this->flash['messages'] = array('success'=> _("Die Zuordnung wurde entfernt"));
        }
        else{
            $this->flash['messages']['error'] = _("Die Zuordnung konnte nicht entfernt werden.");
        }
        
        
        
        $this->redirect(PluginEngine::getLink('opencast/course/index'));
    }


    function scheduler_action()
    {
        require_once 'lib/raumzeit/raumzeit_functions.inc.php';
        Navigation::activateItem('course/opencast/scheduler');
        $navigation = Navigation::getItem('/course/opencast');
        $navigation->setImage('../../'.$this->dispatcher->trails_root.'/images/oc-logo-black.png');
        
        $this->set_title(_("Opencast Aufzeichnungen planen"));
        

        $this->course_id = $_SESSION['SessionSeminar'];
        
        $this->cseries = OCModel::getConnectedSeries($this->course_id);
        $this->dates  =  OCModel::getFutureDates($this->course_id);
        
        $search_client = SearchClient::getInstance();
         
        $workflow_client = WorkflowClient::getInstance();
    }


    function schedule_action($resource_id, $termin_id)
    {

        $this->course_id = Request::get('cid');
        if($GLOBALS['perm']->have_studip_perm('dozent', $this->course_id)){
            $scheduler_client = SchedulerClient::getInstance();
            if($scheduler_client->scheduleEventForSeminar($this->course_id, $resource_id, $termin_id)) {
                $this->flash['messages'] = array('success'=> _("Aufzeichnung wurde geplant."));
            } else {
                $this->flash['messages'] = array('error'=> _("Aufzeichnung konnte nicht geplant werden."));
            }
        } else {
            throw new Exception(_("Sie haben leider keine Berechtigungen um diese Aktion durchzuf�hren"));
        }
        $this->redirect(PluginEngine::getLink('opencast/course/scheduler'));
    }

    function unschedule_action($resource_id, $termin_id)
    {

        $this->course_id = Request::get('cid');
        if($GLOBALS['perm']->have_studip_perm('dozent', $this->course_id)){
            $scheduler_client = SchedulerClient::getInstance();
            if( $scheduler_client->deleteEventForSeminar($this->course_id, $resource_id, $termin_id)) {
                $this->flash['messages'] = array('success'=> _("Die geplante Aufzeichnung wurde entfernt"));
            } else {
                $this->flash['messages'] = array('error'=> _("Die geplante Aufzeichnung konnte nicht entfernt werden."));
            }
        } else {
            throw new Exception(_("Sie haben leider keine Berechtigungen um diese Aktion durchzuf�hren"));
        }

        $this->redirect(PluginEngine::getLink('opencast/course/scheduler'));
    }


    function update_action($resource_id, $termin_id)
    {

        $course_id = Request::get('cid');
        if($GLOBALS['perm']->have_studip_perm('dozent', $course_id)){
            $scheduler_client = SchedulerClient::getInstance();
            $scheduled = OCModel::checkScheduledRecording($course_id, $resource_id, $termin_id);

            if( $scheduler_client->updateEventForSeminar($course_id, $resource_id, $termin_id, $scheduled['event_id'])) {
                $this->flash['messages'] = array('success'=> _("Die geplante Aufzeichnung aktualisiert"));
            } else {
                $this->flash['messages'] = array('error'=> _("Die geplante Aufzeichnung konnte nicht aktualisiert werden."));
            }
        } else {
            throw new Exception(_("Sie haben leider keine Berechtigungen um diese Aktion durchzuf�hren"));
        }

        $this->redirect(PluginEngine::getLink('opencast/course/scheduler'));
    }


    function create_series_action()
    {
        if($GLOBALS['perm']->have_studip_perm('dozent', $this->course_id)){
            $this->series_client = SeriesClient::getInstance();
            if($this->series_client->createSeriesForSeminar($this->course_id)) {
                $this->flash['messages']['success'] = _("Series wurde angelegt");
                
            } else {
                throw new Exception(_("Verbindung zum Series-Service konnte nicht hergestellt werden."));
            }
        } else {
           throw new Exception(_("Sie haben leider keine Berechtigungen um diese Aktion durchzuf�hren"));
        }
        $this->redirect(PluginEngine::getLink('opencast/course/index'));
    }

    function toggle_visibility_action($episode_id, $position) {
        $this->course_id = Request::get('cid');
        $this->user_id = $GLOBALS['auth']->auth['uid'];

        if($GLOBALS['perm']->have_studip_perm('admin', $this->course_id) 
            || OCModel::checkPermForEpisode($episode_id, $this->user_id))
        {
            $visible = OCModel::getVisibilityForEpisode($this->course_id, $episode_id);
            // if visibilty wasn't set before do so...
            if(!$visible){
                OCModel::setVisibilityForEpisode($this->course_id, $episode_id, 'true');
                $visible['visible'] = 'true';
            }

            if($visible['visible'] == 'true'){
               OCModel::setVisibilityForEpisode($this->course_id, $episode_id, 'false');
               $this->flash['messages'] = array('success'=> _("Episode wurde unsichtbar geschaltet"));
            } else {
               OCModel::setVisibilityForEpisode($this->course_id, $episode_id, 'true');
               $this->flash['messages'] = array('success'=> _("Episode wurde sichtbar geschaltet"));
            }
        } else {
            throw new Exception(_("Sie haben leider keine Berechtigungen um diese Aktion durchzuf�hren"));
        }
        $this->redirect(PluginEngine::getLink('opencast/course/index/' . $episode_id));
    }

    function upload_action()
    {
        //TODO this should only work iff an series is connected!
        $this->date = date('Y-m-d');
        $this->hour = date('H');
        $this->minute = date('i');
       
        $scripts = array(
            '/vendor/jquery.fileupload.js',
            '/vendor/jquery.ui.widget.js'
        );
        Navigation::activateItem('course/opencast/upload');
        
        try {
            //check needed services before showing upload form
            UploadClient::getInstance()->checkService();
            IngestClient::getInstance()->checkService();
            MediaPackageClient::getInstance()->checkService();
            SeriesClient::getInstance()->checkService();

            foreach($scripts as $path) {
                $script_attributes = array(
                    'src'   => $GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP'] . 'plugins_packages/elan-ev/OpenCast' . $path);
                PageLayout::addHeadElement('script', $script_attributes, '');
            }

            //TODO: gibt es keine generische Funktion daf�r?
            $this->rel_canonical_path = $GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP'] . 'plugins_packages/elan-ev/OpenCast';
        } catch (Exception $e) {
            $this->flash['error'] = $e->getMessage();
            $this->render_action('_error');
        }
    }


    
    function bulkschedule_action()
    {
        $course_id =  Request::get('cid');
        $action = Request::get('action');
        if($GLOBALS['perm']->have_studip_perm('dozent', $course_id)){
            $dates = Request::getArray('dates');
            foreach($dates as $termin_id => $resource_id){
                switch($action) {
                    case "create":
                        self::schedule($resource_id, $termin_id, $course_id);
                        break;
                    case "update":
                        self::updateschedule($resource_id, $termin_id, $course_id);
                        break;
                    case "delete":
                        self::unschedule($resource_id, $termin_id, $course_id);
                        break;
                }
            }
        } else {
            throw new Exception(_("Sie haben leider keine Berechtigungen um diese Aktion durchzuf�hren"));
        }

        $this->redirect(PluginEngine::getLink('opencast/course/scheduler'));
    }
    
    static function schedule($resource_id, $termin_id, $course_id) {
        $scheduled = OCModel::checkScheduledRecording($course_id, $resource_id, $termin_id);
        if(!$scheduled) {
            $scheduler_client = SchedulerClient::getInstance();

            if($scheduler_client->scheduleEventForSeminar($course_id, $resource_id, $termin_id)) {
                return true;
            } else {
                // TODO FEEDBACK
            }
        }
    }
    
    static function updateschedule($resource_id, $termin_id, $course_id) {

        $scheduled = OCModel::checkScheduledRecording($course_id, $resource_id, $termin_id);
        if($scheduled){
            $scheduler_client = SchedulerClient::getInstance();
            $scheduler_client->updateEventForSeminar($course_id, $resource_id, $termin_id, $scheduled['event_id']);
        } else {
            self::schedule($resource_id, $termin_id, $course_id);
        }  
    }
    
    static function unschedule($resource_id, $termin_id, $course_id) {
        $scheduled = OCModel::checkScheduledRecording($course_id, $resource_id, $termin_id);
        if($scheduled) {
            $scheduler_client = SchedulerClient::getInstance();

            if( $scheduler_client->deleteEventForSeminar($course_id, $resource_id, $termin_id)) {
                return true;
            } else {
                // TODO FEEDBACK
            }
        }
    }
    
    function remove_failed_action($workflow_id) {
        if(OCModel::removeWorkflowIDforCourse($workflow_id, $this->course_id)){
            $this->flash['messages'] = array('success'=> _("Die hochgeladenen Daten wurden gel�scht."));
        } else {
            $this->flash['messages'] = array('error'=> _("Die hochgeladenen Daten konnten nicht gel�scht werden."));
        }
        $this->redirect(PluginEngine::getLink('opencast/course/index/'));
    }


}
?>
