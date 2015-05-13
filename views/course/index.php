<? if($flash['delete']) : ?>
    <?= createQuestion2(sprintf(_('Wollen Sie die Verkn�pfung zur Series "%s" wirklich aufheben?'), utf8_decode($this->connectedSeries[0]['title'])),  array('course_id' => $course_id, 'series_id' => $this->connectedSeries[0]['identifier'], 'delete' => true),array('cancel' => true),PluginEngine::getLink('opencast/course/remove_series/'. get_ticket()))?>

<? endif ?>

<?= $this->render_partial('messages') ?>



<script language="JavaScript">
    OC.initIndexpage();
    OC.initUpload(<?= OC_UPLOAD_CHUNK_SIZE ?>);
</script>

<?
    $sidebar = Sidebar::get ();
    
    if($GLOBALS ['perm']->have_studip_perm ('dozent', $this->course_id))
    {
        $actions = new ActionsWidget ();
        $upload = '';
        if(! empty ($connectedSeries))
        {
            $actions->addLink (_ ("Verkn�pfung aufheben"), PluginEngine::getLink ('opencast/course/index/' . $active_id . '/ /true'), 'icons/16/blue/trash.png');
            if($series_metadata [0] ['schedule'] == '1')
            {
                $actions->addLink (_ ("Medien hochladen"), '#', 'icons/16/blue/upload.png', array (
                        'id' => 'oc_upload_dialog' 
               ));
            }
        } else
        {
            $actions->addLink (_('Neue Series anlegen'), PluginEngine::getLink ('opencast/course/create_series/'), 'icons/16/blue/tools.png');
            $actions->addLink (_ ('Vorhandene Series verkn�pfen'), '#', 'icons/16/blue/group.png', array (
                    'id' => 'oc_config_dialog' 
           ));
        }
        //UOS FEEDBACK
        $actions->addLink (_ ('Feedback abgeben'), '#', 'icons/16/blue/evaluation.png', array (
                          'id' => 'oc_feedback_dialog',
                          'class' => 'tooltip',
                          'title' => _('Geben Sie Feedback zum neuen Player ab!')
        ));
        
        $sidebar->addWidget ($actions);
        Helpbar::get ()->addPlainText ('', _("Hier sehen Sie eine �bersicht ihrer Vorlesungsaufzeichnungen. Sie k�nnen �ber den Unterpunkt Aktionen weitere Medien zur Liste der Aufzeichnungen hinzuf�gen. Je nach Gr��e der Datei kann es einige Zeit in Anspruch nehmen, bis die entsprechende Aufzeichnung in der Liste sichtbar ist. Weiterhin ist es m�glich die ausgew�hlten Sichtbarkeit einer Aufzeichnung innerhalb der Veranstaltung direkt zu �ndern."));
    } else
    {
        Helpbar::get ()->addPlainText ('', _("Hier sehen Sie eine �bersicht ihrer Vorlesungsaufzeichnungen."));
        //UOS tmp
        $actions = new ActionsWidget ();
        $actions->addLink (_ ('Feedback abgeben'), '#', 'icons/16/blue/evaluation.png', array (
                'id' => 'oc_feedback_dialog',
                'class' => 'tooltip',
                'title' => _('Geben Sie Feedback zum neuen Player ab!')
       ));
        $sidebar->addWidget ($actions);
        
    }
?>




<h1>
  <?= _('Vorlesungsaufzeichnungen') ?>
</h1>
<? if(!(empty($ordered_episode_ids))) : ?>

<? foreach($ordered_episode_ids as $oe) :?>
    <? if($oe['id'] == $active_id) :?>
         <? $active = $oe;?>
    <? endif;?>
<? endforeach;?>


<? $visible = OCModel::getVisibilityForEpisode($course_id, $active['id'])?>
<div class="oce_playercontainer">
    <? if($theodul) : ?>
        <iframe src="<?=$embed?>"
                style="border:0px #FFFFFF none;"
                name="Opencast Matterhorn video player"
                scrolling="no"
                frameborder="0"
                marginheight="0px"
                marginwidth="0px"
                width="640"
                height="360"
                allowfullscreen="true"
                webkitallowfullscreen="true"
                mozallowfullscreen="true">
        </iframe>
    <? else: ?>
        <iframe src="<?=$embed?>&hideControls=false"
            style="border: 0px #FFFFFF none;"
            name="Opencast Matterhorn - Media Player" scrolling="no"
            frameborder="0" marginheight="0px" marginwidth="0px"
            width="100%" height="250px">
        </iframe>
     <? endif; ?>
    <br>
    <div class="oce_emetadata">
        <h2 class="oce_title"><?= htmlready(mb_convert_encoding($active['title'], 'ISO-8859-1', 'UTF-8'))?></h2>
        <ul class="oce_contetlist">
            <li><?=_('Aufzeichnungsdatum : ')?> <?=date("d.m.Y H:m",strtotime($active['start']));?> <?=_("Uhr")?></li>
            <li><?=_('Autor : ')?> <?=$active['author'] ? htmlready(mb_convert_encoding($active['author'], 'ISO-8859-1', 'UTF-8'))  : 'Keine Angaben vorhanden';?></li>
            <li><?=_('Beschreibung : ')?> <?=$active['description'] ? htmlready(mb_convert_encoding($active['description'], 'ISO-8859-1', 'UTF-8'))  : 'Keine Beschreibung vorhanden';?></li>
        </ul>
        <div class="ocplayerlink">
            <div style="text-align: left; font-style: italic;">Weitere
                Optionen:</div>
            <div class="button-group">
                <?= Studip\LinkButton::create(_('Erweiterter Player'), URLHelper::getURL('http://'.$engage_player_url), array('target'=> '_blank','class' => 'ocextern')) ?>
                <? if($active['presenter_download']) : ?> 
                    <?= Studip\LinkButton::create(_('Download ReferentIn'), URLHelper::getURL($active['presenter_download']), array('target'=> '_blank', 'class' => 'download presenter')) ?>
                <? endif;?>
                <? if($active['presentation_download']) : ?>
                    <?= Studip\LinkButton::create(_('Download Bildschirm '), URLHelper::getURL($active['presentation_download']), array('target'=> '_blank', 'class' => 'download presentation')) ?>
                <? endif;?>
                <? if($active['audio_download']) :?>
                    <?= Studip\LinkButton::create(_('Download Audio'), URLHelper::getURL($active['audio_download']), array('target'=> '_blank', 'class' => 'download audio')) ?>
                <? endif;?>
                </div>
                <? if($GLOBALS['perm']->have_studip_perm('dozent', $course_id)) :?>
                <div class="button-group">
                    <? if ($visible && $visible['visible'] == 'false') : ?>
                        <?= Studip\LinkButton::create(_('Aufzeichnung sichtbar schalten'), PluginEngine::getLink('opencast/course/toggle_visibility/' . $active_id .'/'. $active['position']), array('class' => 'ocinvisible ocspecial', 'id' => 'oc-togglevis', 'data-episode-id' => $active_id)); ?>
                    <? else : ?>
                        <?= Studip\LinkButton::create(_('Aufzeichnung unsichtbar schalten'), PluginEngine::getLink('opencast/course/toggle_visibility/' . $active_id .'/'. $active['position']), array('class' => 'ocvisible ocspecial', 'id' => 'oc-togglevis', 'data-episode-id' => $active_id,)); ?>
                    <? endif; ?>
                   
                </div>
                <? endif;?>
            </div>
    </div>
</div>

<div id="episodes">
    <ul class="oce_list"
        <?=($GLOBALS['perm']->have_studip_perm('dozent', $course_id)) ? 'id="oce_sortablelist"' : ''?>>
        <? if($GLOBALS['perm']->have_studip_perm('dozent', $course_id) && !empty($states)) :?>
            <? foreach($states as $workflow_id => $state) :?>
            <li class="uploaded oce_item">
                
                <? if($state->state == 'FAILED') : ?>
                    <div class="oce_preview_container">
                        <?=_("Videoverarbeitung fehlerhaft")?>
                    </div>
            <div class="oce_metadatacontainer oce_failedstate">
                <h3 class="oce_metadata"><?= htmlready(mb_convert_encoding($state->mediapackage->title, 'ISO-8859-1', 'UTF-8'))?></h3>
                        <?= Studip\LinkButton::create(_('Daten vom Server entfernen'), PluginEngine::getLink('opencast/course/remove_failed/' . $state->id)); ?></span>
            </div>
                <? else :?>
                <a class="disabled">
                <div class="oce_preview_container">
                    <?=_("Video wird verarbeitet")?>
                </div>
                <div class="oce_metadatacontainer">
                    <h3 class="oce_metadata"><?= htmlready(mb_convert_encoding($state->mediapackage->title, 'ISO-8859-1', 'UTF-8'))?></h3>
                    <span class="oce_metadata"><?=sprintf(_("Hochgeladen am %s"),date("d.m.Y H:m",strtotime($state->mediapackage->start)))?></span>
                </div>
                <? endif; ?>
                </a>
        </li>    
            <? endforeach;?>
        <? endif;?>
        <? foreach($ordered_episode_ids as $pos => $item) : ?>
        <li id="<?=$item['id']?>"
            class="<?=($item['visibility'] != false) ? 'oce_item' : 'hidden_ocvideodiv oce_item'?><?=($item['id'] == $active['id']) ? ' oce_active_li' : ''?>"
            data-courseId="<?=$course_id?>"
            data-visibility="<?=var_export($item['visibility'], true)?>">
            <a
            href="<?= PluginEngine::getLink('opencast/course/index/'. $item['id']) ?>">
                <div>
                    <img
                        class="oce_preview <?=($item['visibility'] == false) ? 'hidden_ocvideo' : ''?>"
                        src="<?=$item['preview']?>">
                </div>
                <div class="oce_metadatacontainer">
                    <h3 class="oce_metadata"><?= htmlready(mb_convert_encoding($item['title'], 'ISO-8859-1', 'UTF-8'))?> <?=($item['visibility'] != false) ? '' : ' (Unsichtbar)'?></h3>
                    <span><?=sprintf(_("Vom %s"),date("d.m.Y H:m",strtotime($item['start'])))?></span>
                </div>
        </a>
        </li>
        <? endforeach; ?>
    </ul>
    <div id="oce_pagination"></div>
</div>
<? else: ?>
    <? if(empty($this->connectedSeries) && $GLOBALS['perm']->have_studip_perm('dozent', $course_id)) :?>
            <?= MessageBox::info(_("Sie haben noch keine Series aus Opencast mit dieser Veranstaltung verkn�pft. Bitte erstellen Sie eine neue Series oder verkn�pfen eine bereits vorhandene Series.")) ?>
    <? else: ?>
        <?=MessageBox::info(_('Es wurden bislang keine Vorlesungsaufzeichnungen bereitgestellt.'));?>
    <? endif;?>
<? endif; ?>


<? if($GLOBALS['perm']->have_studip_perm('dozent', $course_id)) :?>

<div id="upload_dialog" title="<?=_("Medienupload")?>">
<?= $this->render_partial("course/_upload", array('course_id' => $course_id, 'dates' => $dates, 'series_id' => $this->connectedSeries[0]['identifier'])) ?>
</div>

<div id="config_dialog" title="<?=_("Series verk�pfen")?>">
    <?= $this->render_partial("course/_config", array()) ?>
</div>
<? endif;?>

<div id="feedback_dialog" title="<?=_("Neuer Player: Feedback erw�nscht")?>">
  <p>Zum aktuellen Semester haben wir den Player f�r Vorlesungsaufzeichnungen modernisiert. Wir hoffen, dass sie die neuen Funktionen, wie zum Beispiel die M�glichkeit das Video schneller abspielen zu k�nnen, als Verbesserung empfinden. Da es sich aber um ein sehr neues Softwaremodul handelt und technologisch einiges modernisiert wurde, kann es m�glicherweise Probleme geben, die nicht in unserer Qualit�tssicherung aufgefallen sind.</p>

  <p>Wenn sie also Probleme feststellen, w�rden wir sie um Feedback bitten. Schreiben sie uns doch bitte ihr Problem an <a href="mailto:virtuos@uni-osnabrueck.de?subject=Feedback%20zum%20neuen%20Opencastplayer">virtuos@uni-osnabrueck.de</a>. Bitte geben sie wenn m�glich, die angeschaute Aufzeichnung, den Zeitpunkt zu dem die Probleme aufgetreten sind, die Art ihrer Internetverbindung, ihren Internetbrowser inkl. der Versionsnummer, ihr Betriebssystem und ein paar grobe Daten zum ihrem Rechner (Alter, CPU, RAM) an.</p>

  <p>Vielen Dank f�r ihre Unterst�tzung!</p>
</div>