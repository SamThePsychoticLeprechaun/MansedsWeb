<?php

/* 
 * Copyright (C) 2016 Matthew Marshall
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

    namespace Sycamore\ACL\Listeners;
    
    use Sycamore\Application;
    use Sycamore\ACL\ListenerInterface;
    use Sycamore\Utils\TableCache;
    use Sycamore\Visitor;
    
    use Zend\EventManager\EventInterface;
    
    class ActionListener implements ListenerInterface
    {
        public function prepare(\Sycamore\ACL\ACL $acl) {
            Application::getSharedEventsManager()->attach("action", array("preExecuteGet", "preExecutePost", "preExecutePut", "preExecuteDelete"), function (EventInterface $event) use ($acl) {
                // Stop propogation - we want last say!
                $event->stopPropogation();
                
                // Construct action key.
                $actionKey = get_class($event->getTarget()) . "\\" . $event->getName();
                
                // Get action by its key.
                $actionTable = TableCache::getTableFromCache("ActionTable");
                $action = $actionTable->getByKey($actionKey);
                
                // If visitor is not logged in, then only allow open routes.
                if (!Visitor::getInstance()->isLoggedIn) {
                    if ($action->open) {
                        return true;
                    }
                    return false;
                }
                
                // If visitor is a super user, then allow.
                if (Visitor::getInstance()->superUser) {
                    return true;
                }
                
                // Get associated ACL groups.
                $aclGroupActionMaps = $acl->getACLGroupActionMapsByActionId($action->id);
                
                // Check if any ACL groups deny access, or if at least one allows otherwise.
                $allowed = false;
                foreach ($aclGroupActionMaps as $aclGroupActionMap) {
                    if ($acl->userHasACLGroup(Visitor::getInstance()->id, $aclGroupActionMap->groupId)) {
                        if ($aclGroupActionMap->state < 0) {
                            return false;
                        } else if ($aclGroupActionMap->state > 0) {
                            $allowed = true;
                        }
                    }
                }
                return $allowed;
            });
        }
    }
