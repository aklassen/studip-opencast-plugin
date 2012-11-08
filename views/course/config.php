<? if (isset($message)): ?>
  <?= MessageBox::success($message) ?>
<? endif ?>
<? if ($flash['question']) : ?>
    <?= $flash['question'] ?>
<? endif  ?>


<?
$infobox_content = array(array(
    'kategorie' => _('Hinweise:'),
    'eintrag'   => array(array(
        'icon' => 'icons/16/black/info.png',
        'text' => _("Hier k�nnen Sie die Veranstaltung mit einer Series in Opencast Matterhorn verkn�pfen. Sie k�nnen entweder aus vorhandenen Series w�hlen oder eine Series f�r diese Veranstaltung anlegen.")
    ))
));
?>
<script language="JavaScript">
OC.initAdmin();
</script>

<h3><?=_('Verwaltung der eingebundenen Vorlesungsaufzeichnungen')?></h3>

<?if (true || !empty($this->connectedSeries)) : ?>
    <?= MessageBox::info(sprintf(_("Sie haben noch keine Series aus Opencast mit dieser Veranstaltung verkn�pft.
                            Bitte verkn�pfen eine bereits vorhandene Series oder %s erstellen Sie eine neue.%s"), 
                         '<a href="'.PluginEngine::getLink('opencast/course/create_series/') . '">', '</a>')) ?>
    <div id="admin-accordion">
        <h3><?=_('W�hlen Sie unten eine Series aus, die Sie mit der aktuellen Veranstaltung verkn�pfen m�chten')?>:</h3>
        <?= $this->render_partial("course/_connectedSeries", array('course_id' => $course_id, 'connectedSeries' => $connectedSeries, 'unonnectedSeries' => $unonnectedSeries, 'series_client' => $series_client)) ?>
    </div>


<? elseif(!$connected && !empty($this->cseries)) : ?>
    <h4> <?=_('Verkn�pfte Serie:')?> </h4>
    <? $x = 'http://purl.org/dc/terms/'; ?>
    <div>
        <?= $serie_name->$x->title[0]->value ?>
        <a href="<?=PluginEngine::getLink('opencast/course/remove_series/'.$serie_id.'/true' ) ?>">
            <?= Assets::img('icons/16/blue/trash.png', array('title' => _("Verkn�pfung aufheben"))) ?>
        </a>
    </div>

<? elseif($connected): ?>
    <h4> <?=_('Verkn�pfte Serie:')?> </h4>
    <? $x = 'http://purl.org/dc/terms/'; ?>
    <div>
        <?= $serie_name->$x->title[0]->value ?>
        <a href="<?=PluginEngine::getLink('opencast/course/remove_series/'.$serie_id.'/false' ) ?>">
            <?= Assets::img('icons/16/blue/trash.png', array('title' => _("Verkn�pfung aufheben"))) ?>
        </a>
    </div>
    <? if(empty($episodes)) :?>
        <?= MessageBox::info(_("Es sind bislang keine Vorlesungsaufzeichnungen verf�gbar.")) ?>
    <? else: ?>
        <h4> <?=_('Verf�gbare Vorlesungsaufzeichnungen bearbeiten:')?> </h4>

        <table class="default">
                <tr>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
                <? foreach($episodes as $episode) :?>

                    <? if(isset($episode->mediapackage)) :?>
                    <tr>
                        <td>
                            <?=$episode->mediapackage->title ?>
                        </td>
                        <td>
                            <?$visible = OCModel::getVisibilityForEpisode($course_id, $episode->mediapackage->id); ?>
                            <? if($visible['visible'] == 'true') : ?>
                                <?=_("Sichtbar")?>
                            <? else : ?>
                                <?=_("Unsichtbar")?>
                            <? endif; ?>
                        </td>

                        <td>
                            <a href="<?=PluginEngine::getLink('opencast/course/toggle_visibility/'.$episode->mediapackage->id ) ?>">
                                <?= Assets::img('icons/16/blue/visibility-visible.png', array('title' => _("Aufzeichnung unsichtbar schalten"))) ?>
                            </a>
                        </td>
                    </tr>
                    <? endif ;?>
                <? endforeach; ?>

       </table>
    <? endif;?>
<? endif; ?>

<?$infobox = array('picture' => 'infobox/administration.jpg', 'content' => $infobox_content); ?>
