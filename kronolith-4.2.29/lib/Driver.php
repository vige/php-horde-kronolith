<?php
/**
 * Kronolith_Driver defines an API for implementing storage backends for
 * Kronolith.
 *
 * Copyright 1999-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @package Kronolith
 */
class Kronolith_Driver
{
    /**
     * The current calendar.
     *
     * @var string
     */
    public $calendar;

    /**
     * The HTML background color to be used for this event.
     *
     * @var string
     */
    public $backgroundColor = '#ddd';

    /**
     * The HTML foreground color to be used for this event.
     *
     * @var string
     */
    public $foregroundColor = '#000';

    /**
     * A hash containing any parameters for the current driver.
     *
     * @var array
     */
    protected $_params = array();

    /**
     * An error message to throw when something is wrong.
     *
     * @var string
     */
    private $_errormsg;

    /**
     * Constructor.
     *
     * Just stores the $params in our newly-created object. All other work is
     * done by {@link initialize()}.
     *
     * @param array $params     Any parameters needed for this driver.
     * @param string $errormsg  A custom error message to use.
     */
    public function __construct(array $params = array(), $errormsg = null)
    {
        $this->_params = $params;
        if ($errormsg === null) {
            $this->_errormsg = _("The Calendar backend is not currently available.");
        } else {
            $this->_errormsg = $errormsg;
        }
    }

    /**
     * Returns a configuration for this driver.
     *
     * @param string $param  A parameter name.
     *
     * @return mixed  The parameter value or null if not set.
     */
    public function getParam($param)
    {
        return isset($this->_params[$param]) ? $this->_params[$param] : null;
    }

    /**
     * Sets a configuration for this driver.
     *
     * @param string $param  A parameter name.
     * @param mixed $value   The parameter value.
     */
    public function setParam($param, $value)
    {
        $this->_params[$param] = $value;
    }

    /**
     * Sets all configuration parameters for this driver.
     *
     * @param string $params  A parameters hash.
     */
    public function setParams($params)
    {
        $this->_params = $params;
    }

    /**
     * Selects a calendar as the currently opened calendar.
     *
     * @param string $calendar  A calendar identifier.
     */
    public function open($calendar)
    {
        $this->calendar = $calendar;
    }

    /**
     * Returns the background color of the current calendar.
     *
     * @return string  The calendar color.
     */
    public function backgroundColor()
    {
        return '#dddddd';
    }

    /**
     * Returns the colors of the current calendar.
     *
     * @return array  The calendar background and foreground color.
     */
    public function colors()
    {
        $color = $this->backgroundColor();
        return array($color, Kronolith::foregroundColor($color));
    }

    /**
     * Returns whether this driver supports per-event timezones.
     *
     * @return boolean  Whether this drivers suppports per-event timezones.
     */
    public function supportsTimezones()
    {
        return false;
    }

    /**
     * Searches a calendar.
     *
     * @param object $query  An object with the criteria to search for.
     * @param boolean $json  Store the results of the events' toJson() method?
     *
     * @return array An array of search results keyed by date, with each date
     *               containing an array of Kronolith_Events occuring on that
     *               date.
     * @throws Kronolith_Exception
     */
    public function search($query, $json = false)
    {
        /* Our default implementation first gets <em>all</em> events in a
         * specific period, and then filters based on the actual values that
         * are filled in. Drivers can optimize this behavior if they have the
         * ability. */
        $results = array();

        $events = $this->listEvents(
            (!empty($query->start) ? $query->start : null),
            (!empty($query->end) ? $query->end : null));
        foreach ($events as $day_events) {
            foreach ($day_events as $event) {
                if ((((!isset($query->start) ||
                       $event->end->compareDateTime($query->start) > 0) &&
                      (!isset($query->end) ||
                       $event->end->compareDateTime($query->end) < 0)) ||
                     ($event->recurs() &&
                      $event->end->compareDateTime($query->start) >= 0 &&
                      $event->start->compareDateTime($query->end) <= 0)) &&
                    (empty($query->title) ||
                     stristr($event->getTitle(), $query->title)) &&
                    (empty($query->location) ||
                     stristr($event->location, $query->location)) &&
                    (empty($query->description) ||
                     stristr($event->description, $query->description)) &&
                    (empty($query->creator) ||
                     stristr($event->creator, $query->creator)) &&
                    (!isset($query->status) ||
                     $event->status == $query->status) &&
                    (empty($query->baseid) ||
                     $event->baseid == $query->baseid)) {
                    Kronolith::addEvents($results, $event, $event->start, $event->end, false, $json, false);
                }
            }
        }

        return $results;
    }

    /**
     * Finds the next recurrence of $eventId that's after $afterDate.
     *
     * @param string $eventId        The ID of the event to fetch.
     * @param Horde_Date $afterDate  Return events after this date.
     *
     * @return Horde_Date|boolean  The date of the next recurrence or false if
     *                             the event does not recur after $afterDate.
     * @throws Kronolith_Exception
     * @throws Horde_Exception_NotFound
     */
    public function nextRecurrence($eventId, $afterDate)
    {
        $event = $this->getEvent($eventId);
        return $event->recurs() ? $event->recurrence->nextRecurrence($afterDate) : false;
    }

    /**
     * Returns the number of events in the current calendar.
     *
     * @return integer  The number of events.
     * @throws Kronolith_Exception
     */
    public function countEvents()
    {
        $count = 0;
        foreach ($this->listEvents() as $dayevents) {
            $count += count($dayevents);
        }
        return $count;
    }

    /**
     * Stub to initiate a driver.
     *
     * @throws Kronolith_Exception
     */
    public function initialize()
    {
        return true;
    }

    /**
     * Stub to be overridden in the child class.
     *
     * @throws Kronolith_Exception
     * @throws Horde_Exception_NotFound
     */
    public function getEvent($eventId = null)
    {
        throw new Kronolith_Exception($this->_errormsg);
    }

    /**
     * Stub to be overridden in the child class.
     *
     * @throws Kronolith_Exception
     * @throws Horde_Exception_NotFound
     */
    public function getByUID($uid, $calendars = null, $getAll = false)
    {
        throw new Kronolith_Exception($this->_errormsg);
    }

    /**
     * Stub to be overridden in the child class.
     *
     * @throws Kronolith_Exception
     */
    public function listAlarms($date, $fullevent = false)
    {
        throw new Kronolith_Exception($this->_errormsg);
    }

    /**
     * Lists all events in the time range, optionally restricting results to
     * only events with alarms.
     *
     * @param Horde_Date $startDate  The start of range date.
     * @param Horde_Date $endDate    The end of date range.
     * @param array $options         Additional options:
     *   - show_recurrence: (boolean) Return every instance of a recurring
     *                       event?
     *                      DEFAULT: false (Only return recurring events once
     *                      inside $startDate - $endDate range)
     *   - has_alarm:       (boolean) Only return events with alarms.
     *                      DEFAULT: false (Return all events)
     *   - json:            (boolean) Store the results of the event's toJson()
     *                      method?
     *                      DEFAULT: false
     *   - cover_dates:     (boolean) Add the events to all days that they
     *                      cover?
     *                      DEFAULT: true
     *   - hide_exceptions: (boolean) Hide events that represent exceptions to
     *                      a recurring event.
     *                      DEFAULT: false (Do not hide exception events)
     *   - fetch_tags:      (boolean) Fetch tags for all events.
     *                      DEFAULT: false (Do not fetch event tags)
     *
     * @throws Kronolith_Exception
     */
    public function listEvents(Horde_Date $startDate = null,
                               Horde_Date $endDate = null,
                               array $options = array())
    {
        // Defaults
        $options = array_merge(array(
            'show_recurrence' => false,
            'has_alarm' => false,
            'json' => false,
            'cover_dates' => true,
            'hide_exceptions' => false,
            'fetch_tags' => false), $options);

        return $this->_listEvents($startDate, $endDate, $options);
    }

    /**
     * Stub to be overridden in concrete class.
     *
     * @param Horde_Date $startDate  The start of range date.
     * @param Horde_Date $endDate    The end of date range.
     * @param array $options         Additional options:
     *   - show_recurrence: (boolean) Return every instance of a recurring event?
     *                      DEFAULT: false (Only return recurring events once
     *                      inside $startDate - $endDate range).
     *   - has_alarm: (boolean) Only return events with alarms.
     *                DEFAULT: false (Return all events)
     *   - json: (boolean) Store the results of the event's toJson() method?
     *           DEFAULT: false
     *   - cover_dates: (boolean) Add the events to all days that they cover?
     *                  DEFAULT: true
     *   - hide_exceptions: (boolean) Hide events that represent exceptions to
     *                      a recurring event.
     *                      DEFAULT: false (Do not hide exception events)
     *   - fetch_tags: (boolean) Fetch tags for all events.
     *                 DEFAULT: false (Do not fetch event tags)
     *
     * @throws Kronolith_Exception
     */
    protected function _listEvents(
        Horde_Date $startDate = null, Horde_Date $endDate = null, array $options = array())
    {
        throw new Kronolith_Exception($this->_errormsg);
    }

    /**
     * Saves an event in the backend.
     *
     * If it is a new event, it is added, otherwise the event is updated.
     *
     * @param Kronolith_Event $event  The event to save.
     *
     * @return string  The event id.
     * @throws Horde_Mime_Exception
     * @throws Kronolith_Exception
     */
    public function saveEvent(Kronolith_Event $event)
    {
        if (empty($event->start) || $event->start->year <= 0 ||
            empty($event->end) || $event->end->year <= 0) {
            throw new Kronolith_Exception(_("Invalid date"));
        }
        if ($event->stored || $event->exists()) {
            // If this event recurs and has bound exceptions, we must make sure
            // that the exceptionoriginaldate is updated in those exceptions as
            // well. See Bug: 13512
            if ($event->recurs()) {
                foreach ($event->boundExceptions() as $bound) {
                    $t = $event->start->strftime('%T');
                    $bound->exceptionoriginaldate = new Horde_Date($bound->start->strftime('%Y-%m-%d') . 'T' . $t);
                    $bound->save();
                }
            }
            return $this->_updateEvent($event);
        }

        return $this->_addEvent($event);
    }

    /**
     * Stub to be overridden in the child class.
     *
     * @throws Kronolith_Exception
     */
    protected function _addEvent(Kronolith_Event $event)
    {
        throw new Kronolith_Exception($this->_errormsg);
    }

    /**
     * Stub to be overridden in the child class.
     *
     * @throws Kronolith_Exception
     */
    protected function _updateEvent(Kronolith_Event $event)
    {
        throw new Kronolith_Exception($this->_errormsg);
    }

    /**
     * Stub for child class to override if it can implement.
     *
     * @throws Kronolith_Exception
     */
    public function exists($uid, $calendar_id = null)
    {
        throw new BadMethodCallException('Not supported');
    }

    /**
     * Moves an event to a new calendar.
     *
     * @param string $eventId      The event to move.
     * @param string $newCalendar  The new calendar.
     *
     * @throws Kronolith_Exception
     * @throws Horde_Exception_NotFound
     */
    public function move($eventId, $newCalendar)
    {
        $event = $this->_move($eventId, $newCalendar);

        /* Attempt to move any bound exceptions. */
        foreach ($event->boundExceptions() as $exception) {
            $this->move($exception->id, $newCalendar);
        }

        /* Log the moving of this item in the history log. */
        $uid = $event->uid;
        if ($uid) {
            $history = $GLOBALS['injector']->getInstance('Horde_History');
            try {
                $history->log('kronolith:' . $event->calendar . ':' . $uid, array('action' => 'delete'), true);
                $history->log('kronolith:' . $newCalendar . ':' . $uid, array('action' => 'add'), true);
            } catch (Exception $e) {
                Horde::log($e, 'ERR');
            }
        }
    }

    /**
     * Stub to be overridden in the child class.
     *
     * @throws Kronolith_Exception
     */
    protected function _move($eventId, $newCalendar)
    {
        throw new BadMethodCallException('Not supported');
    }

    /**
     * Stub to be overridden in the child class.
     *
     * Note: This method only "Purges" the calendar - removing the
     * events, it doesn't remove the calendar itself.
     *
     * @throws Kronolith_Exception
     */
    public function delete($calendar)
    {
        throw new BadMethodCallException('Not supported');
    }

    /**
     * Deletes an event.
     *
     * @param mixed $eventId   Either the event id to delete, or the event
     *                         object.
     * @param boolean $silent  Don't send notifications, used when deleting
     *                         events in bulk from maintenance tasks.
     *
     * @throws Kronolith_Exception
     * @throws Horde_Exception_NotFound
     * @throws Horde_Mime_Exception
     */
    public function deleteEvent($eventId, $silent = false)
    {
        $event = $this->_deleteEvent($eventId, $silent);
        if (!$event) {
            return;
        }

        /* Log the deletion of this item in the history log. */
        if ($event->uid) {
            try {
                $GLOBALS['injector']->getInstance('Horde_History')->log('kronolith:' . $this->calendar . ':' . $event->uid, array('action' => 'delete'), true);
            } catch (Exception $e) {
                Horde::log($e, 'ERR');
            }
        }

        /* Remove the event from any resources that are attached to it. */
        $resources = $event->getResources();
        if (count($resources)) {
            $rd = Kronolith::getDriver('Resource');
            foreach ($resources as $uid => $resource) {
                if ($resource['response'] !== Kronolith::RESPONSE_DECLINED &&
                    $resource['response'] !== Kronolith::RESPONSE_NONE) {
                    try {
                        $r = $rd->getResource($uid);
                        $r->removeEvent($event);
                    } catch (Horde_Exception_NotFound $e) {
                    } catch (Kronolith_Exception $e) {
                    }
                }
            }
        }

        /* Remove any pending alarms. */
        $GLOBALS['injector']->getInstance('Horde_Alarm')->delete($event->uid);

        /* Remove any tags */
        $tagger = Kronolith::getTagger();
        $tagger->replaceTags($event->uid, array(), $event->creator, Kronolith_Tagger::TYPE_EVENT);

        /* Tell content we removed the object */
        $GLOBALS['injector']->getInstance('Content_Objects_Manager')
            ->delete(array($event->uid), Kronolith_Tagger::TYPE_EVENT);

        /* Remove any geolocation data. */
        try {
            $GLOBALS['injector']->getInstance('Kronolith_Geo')->deleteLocation($event->id);
        } catch (Kronolith_Exception $e) {
        }

        /* Remove any CalDAV mappings. */
        try {
            $davStorage = $GLOBALS['injector']
                ->getInstance('Horde_Dav_Storage');
            try {
                $davStorage
                    ->deleteInternalObjectId($event->id, $event->calendar);
            } catch (Horde_Exception $e) {
                Horde::log($e);
            }
        } catch (Horde_Exception $e) {
        }

        /* See if this event represents an exception - if so, touch the base
         * event's history. The $isRecurring check is to prevent an infinite
         * loop in the off chance that an exception is entered as a recurring
         * event.
         */
        if ($event->baseid && !$event->recurs()) {
            try {
                $GLOBALS['injector']->getInstance('Horde_History')->log('kronolith:' . $this->calendar . ':' . $event->baseid, array('action' => 'modify'), true);
            } catch (Exception $e) {
                Horde::log($e, 'ERR');
            }
        }
    }

    /**
     * Stub to be overridden in the child class.
     *
     * @param mixed $eventId   Either the event id to delete, or the event
     *                         object.
     * @param boolean $silent  Don't send notifications, used when deleting
     *                         events in bulk from maintenance tasks.
     *
     * @return Kronolith_Event  Returns the deleted event.
     * @throws Kronolith_Exception
     * @throws Horde_Exception_NotFound
     * @throws Horde_Mime_Exception
     */
    protected function _deleteEvent($eventId)
    {
    }

    /**
     * Wrapper for sending notifications, so that we can overwrite this action
     * in Kronolith_Driver_Resource.
     *
     * @param Kronolith_Event $event
     * @param string $action
     */
    protected function _handleNotifications(Kronolith_Event $event, $action)
    {
        Kronolith::sendNotification($event, $action);
    }

    /**
     * Stub to be overridden in the child class if it can implement.
     *
     * @throws Kronolith_Exception
     */
    public function filterEventsByCalendar($uids, $calendar)
    {
        throw new BadMethodCallException('Not supported');
    }

    /**
     * Stub for child class to override if it can implement.
     *
     * @todo Remove in Kronolith 4.0
     * @deprecated  Now lives in Kronolith_Application::
     * @throws Kronolith_Exception
     */
    public function removeUserData($user)
    {
        throw new BadMethodCallException('Deprecated.');
    }

    /**
     * Helper function to update an existing event's tags to tagger storage.
     *
     * @param Kronolith_Event $event  The event to update
     */
    protected function _updateTags(Kronolith_Event $event)
    {
        Kronolith::getTagger()->replaceTags($event->uid, $event->tags, $event->creator, Kronolith_Tagger::TYPE_EVENT);

        // Resources don't currently have owners, so can't tag as owner.
        if ($event->calendarType == 'resource') {
            return;
        }

        // Add tags again, but as the share owner (replaceTags removes ALL tags)
        try {
            $cal = $GLOBALS['injector']->getInstance('Kronolith_Shares')->getShare($event->calendar);
        } catch (Horde_Share_Exception $e) {
            throw new Kronolith_Exception($e);
        }
        Kronolith::getTagger()->tag($event->uid, $event->tags, $cal->get('owner'), Kronolith_Tagger::TYPE_EVENT);
    }

    /**
     * Helper function to add tags from a newly creted event to the tagger.
     *
     * @param Kronolith_Event $event  The event to save tags to storage for.
     */
    protected function _addTags(Kronolith_Event $event)
    {
        $tagger = Kronolith::getTagger();
        $tagger->tag($event->uid, $event->tags, $event->creator, Kronolith_Tagger::TYPE_EVENT);

        // Resources don't currently have owners, so can't tag as owner.
        if ($event->calendarType == 'resource') {
            return;
        }

        // Add tags again, but as the share owner.
        try {
            $cal = $GLOBALS['injector']->getInstance('Kronolith_Shares')->getShare($event->calendar);
        } catch (Horde_Share_Exception $e) {
            Horde::log($e->getMessage(), 'ERR');
            throw new Kronolith_Exception($e);
        }

        if ($cal->get('owner') != $event->creator) {
            $tagger->tag($event->uid, $event->tags, $cal->get('owner'), 'event');
        }
    }
}
