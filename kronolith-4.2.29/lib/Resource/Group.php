<?php
/**
 * Kronolith_Resource implementation to represent a group of similar resources.
 *
 * Copyright 2009-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Kronolith
 */
class Kronolith_Resource_Group extends Kronolith_Resource_Base
{
    /**
     *
     * @var Kronolith_Driver_Resource
     */
    private $_driver;

    /**
     * Local cache for event that accepts the invitation.
     * @TODO: probably want to cache this in the session since we will typically
     *        need to do this twice: once when adding the resource to the
     *        attendees form, and once when actually saving the event.
     *
     * @var Kronolith_Resource_Single
     */
    private $_selectedResource;

    /**
     * Const'r
     *
     * @param $params
     *
     * @return Kronolith_Resource
     */
    public function __construct(array $params)
    {
        $params['resource_type'] = 'Group';
        parent::__construct($params);
        $this->_driver = $this->getDriver();
    }

    /**
     * Override the get method to see if we have a selected resource. If so,
     * return the resource's property value, otherwise, return the group's
     * property value.
     *
     * @param string $property  The property to get.
     *
     * @return mixed  The requested property's value.
     */
    public function get($property)
    {
        if (empty($this->_selectedResource)) {
            return parent::get($property);
        } else {
            return $this->_selectedResource->get($property);
        }
    }

    /**
     * Obtain the resource's internal identifier, taking into account whether or
     * not we have validated/selected a resource for this group yet.
     *
     * @return string The id.
     */
    public function getId()
    {
        if (!empty($this->_selectedResource)) {
            return $this->_selectedResource->getId();
        } else {
            return parent::getId();
        }
    }

    /**
     * Determine if the resource is free during the time period for the
     * supplied event.
     *
     * @param Kronolith_Event $event  The event to check availability for.
     *
     * @return boolean
     * @throws Kronolith_Exception
     */
    public function isFree(Kronolith_Event $event)
    {
        if (is_array($event)) {
            $start = $event['start'];
            $end = $event['end'];
        } else {
            $start = $event->start;
            $end = $event->end;
        }

        /* Get all resources that are included in this category */
        $resources = $this->get('members');

        /* Iterate over all resources until one with no conflicts is found */
        foreach ($resources as $resource_id) {
            $conflict = false;
            try {
                $resource = $this->_driver->getResource($resource_id);
            } catch (Horde_Exception_NotFound $e) {
                continue;
            }
            $busy = Kronolith::getDriver('Resource', $resource->get('calendar'))
                ->listEvents($start, $end, array('show_recurrence' => true));

            /* No events at all during time period for requested event */
            if (!count($busy)) {
                $this->_selectedResource = $resource;
                return true;
            }

            /* Check for conflicts, ignoring the conflict if it's for the
             * same event that is passed. */
            if (!is_array($event)) {
                $uid = $event->uid;
            } else {
                $uid = 0;
            }

            foreach ($busy as $events) {
                foreach ($events as $e) {
                    if (!($e->status == Kronolith::STATUS_CANCELLED ||
                          $e->status == Kronolith::STATUS_FREE) &&
                         $e->uid !== $uid) {

                         if (!($e->start->compareDateTime($end) >= 0) &&
                             !($e->end->compareDateTime($start) <= 0)) {

                            // Not free, continue to the next resource
                            $conflict = true;
                            break 2;
                         }
                    }
                }
            }

            if (!$conflict) {
                /* No conflict detected for this resource */
                $this->_selectedResource = $resource;
                return true;
            }
        }

        /* No resource found without conflicts */
        return false;
    }

    /**
     * Adds $event to an available member resource's calendar.
     *
     * @param Kronolith_Event $event
     *
     * @throws Kronolith_Exception
     */
    public function addEvent(Kronolith_Event $event)
    {
        if (!empty($this->_selectedResource)) {
            $this->_selectedResource->addEvent($event);
        } else {
            throw new LogicException('Events should be added to the Single resource object, not directly to the Group object.');
        }
    }

    /**
     * Remove this event from resource's calendar
     *
     * @param Kronolith_Event $event
     *
     * @throws Kronolith_Exception
     */
    public function removeEvent(Kronolith_Event $event)
    {
        throw new BadMethodCallException('Unsupported');
    }

    /**
     * Obtain the freebusy information for this resource.
     *
     * @throws Kronolith_Exception
     */
    public function getFreeBusy($startstamp = null, $endstamp = null, $asObject = false, $json = false)
    {
        throw new BadMethodCallException('Unsupported');
    }

    /**
     * Sets the current resource's id. Must not be an existing resource.
     *
     * @param integer $id  The id for this resource
     *
     * @throws Kronolith_Exception
     */
    public function setId($id)
    {
        if (empty($this->_id)) {
            $this->_id = $id;
        } else {
            throw new LogicException('Resource already exists. Cannot change the id.');
        }
    }

    /**
     * Group resources only make sense for RESPONSETYPE_AUTO
     *
     * @see lib/Resource/Kronolith_Resource_Base#getResponseType()
     */
    public function getResponseType()
    {
        return Kronolith_Resource::RESPONSETYPE_AUTO;
    }

}
