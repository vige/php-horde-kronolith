<?php
/**
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Jan Schneider <jan@horde.org>
 */

function _no_access($status, $reason, $body)
{
    header('HTTP/1.0 ' . $status . ' ' . $reason);
    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
<html><head>
<title>$status $reason</title>
</head><body>
<h1>$reason</h1>
<p>$body</p>
</body></html>";
    exit;
}

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('kronolith', array('authentication' => 'none', 'session_control' => 'readonly'));

$calendar = Horde_Util::getFormData('c');
$endDate = Horde_Util::getFormData('e');

try {
    $share = $injector->getInstance('Kronolith_Shares')->getShare($calendar);
} catch (Exception $e) {
    _no_access(404, 'Not Found',
               sprintf(_("The requested feed (%s) was not found on this server."),
                       htmlspecialchars($calendar)));
}
if (!$share->hasPermission($GLOBALS['registry']->getAuth(), Horde_Perms::READ)) {
    if ($GLOBALS['registry']->getAuth()) {
        _no_access(403, 'Forbidden',
                   sprintf(_("Permission denied for the requested feed (%s)."),
                           htmlspecialchars($calendar)));
    } else {
        $auth = $injector->getInstance('Horde_Core_Factory_Auth')->create();
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $user = $_SERVER['PHP_AUTH_USER'];
            $pass = $_SERVER['PHP_AUTH_PW'];
        } elseif (isset($_SERVER['Authorization'])) {
            $hash = str_replace('Basic ', '', $_SERVER['Authorization']);
            $hash = base64_decode($hash);
            if (strpos($hash, ':') !== false) {
                list($user, $pass) = explode(':', $hash, 2);
            }
        }

        if (!isset($user) ||
            !$auth->authenticate($user, array('password' => $pass))) {
            header('WWW-Authenticate: Basic realm="' . $registry->get('name') . ' Feeds"');
            _no_access(401, 'Unauthorized',
                       sprintf(_("Login required for the requested feed (%s)."),
                               htmlspecialchars($calendar)));
        }
    }
}

$feed_type = basename(Horde_Util::getFormData('type'));
if (empty($feed_type)) {
    // If not specified, default to Atom.
    $feed_type = 'atom';
}

$startDate = new Horde_Date(time());
if ($endDate) {
    try {
        $endDate = new Horde_Date($endDate);
    } catch (Horde_Date_Exception $e) {
        $endDate = new Horde_Date($startDate);
    }
} else {
    $endDate = new Horde_Date($startDate);
}

try {
    $events = Kronolith::listEvents(
        $startDate,
        $endDate,
        array($calendar),
        array('show_recurrence' => true, 'cover_dates' => false)
    );
} catch (Exception $e) {
    Horde::log($e, 'ERR');
    $events = array();
}

if (isset($conf['urls']['pretty']) && $conf['urls']['pretty'] == 'rewrite') {
    $self_url = Horde::url('feed/' . $calendar, true, -1);
} else {
    $self_url = Horde::url('feed/index.php', true, -1)
        ->add('c', $calendar);
}

$owner = $share->get('owner');
$identity = $injector->getInstance('Horde_Core_Factory_Identity')->create($owner);
$history = $injector->getInstance('Horde_History');
$now = new Horde_Date(time());

$template = $injector->createInstance('Horde_Template');
$template->set('updated', $now->format(DATE_ATOM));
$template->set('kronolith_name', 'Kronolith');
$template->set('kronolith_version', $registry->getVersion());
$template->set('kronolith_uri', 'http://www.horde.org/kronolith/');
$template->set('kronolith_icon', Horde::url(Horde_Themes::img('kronolith.png'), true, -1));
$template->set('xsl', Horde_Themes::getFeedXsl());
$template->set('calendar_name', htmlspecialchars($share->get('name')));
$template->set('calendar_desc', htmlspecialchars($share->get('desc')), true);
$template->set('calendar_owner', htmlspecialchars($identity->getValue('fullname')));
$template->set('calendar_email', htmlspecialchars($identity->getValue('from_addr')), true);
$template->set('self_url', $self_url);

$twentyFour = $prefs->getValue('twentyFor');
$entries = array();
foreach ($events as $date => $day_events) {
    foreach ($day_events as $id => $event) {
        /* Modification date. */
        $modified = $history->getActionTimestamp('kronolith:' . $calendar . ':'
                                                 . $event->uid, 'modify');
        if (!$modified) {
            $modified = $history->getActionTimestamp('kronolith:' . $calendar . ':'
                                                     . $event->uid, 'add');
        }
        $modified = new Horde_Date($modified);
        /* Description. */
        $desc = $event->isPrivate() ? '' : htmlspecialchars($event->description);
        if (strlen($desc)) {
            $desc .= '<br /><br />';
        }
        /* Time. */
        $desc .= _("When:") . ' ' . $event->start->strftime($prefs->getValue('date_format')) . ' ' . $event->start->format($twentyFour ? 'H:i' : 'h:ia') . _(" to ");
        if ($event->start->compareDate($event->end->timestamp()) == 0) {
            $desc .= $event->end->format($twentyFour ? 'H:i' : 'h:ia');
        } else {
            $desc .= $event->end->strftime($prefs->getValue('date_format')) . ' ' . $event->end->format($twentyFor ? 'H:i' : 'h:ia');
        }
        /* Attendees. */
        if (!$event->isPrivate()) {
            $attendees = Kronolith::getAttendeeEmailList($event->attendees);
            if (count($attendees)) {
                $desc .= '<br />' . _("Who:") . ' ' . htmlspecialchars(strval($attendees));
            }
            if (strlen($event->location)) {
                $desc .= '<br />' . _("Where:") . ' ' . htmlspecialchars($event->location);
            }
        }
        $desc .= '<br />' . _("Event Status:") . ' ' . Kronolith::statusToString($event->status);

        $entries[$id . $date]['title'] = htmlspecialchars($event->getTitle());
        $entries[$id . $date]['desc'] = htmlspecialchars($desc);
        $entries[$id . $date]['url'] = htmlspecialchars(Horde::url($event->getViewUrl(), true, -1));
        $entries[$id . $date]['modified'] = $modified->format(DATE_ATOM);
    }
}
$template->set('entries', $entries, true);

$browser->downloadHeaders($calendar . '.xml', 'text/xml', true);
echo $template->fetch(KRONOLITH_TEMPLATES . '/feeds/' . $feed_type . '.xml');
