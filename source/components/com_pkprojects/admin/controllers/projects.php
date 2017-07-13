<?php
/**
 * @package      pkg_projectknife
 * @subpackage   com_pkprojects
 *
 * @author       Tobias Kuhn (eaxs)
 * @copyright    Copyright (C) 2015-2017 Tobias Kuhn. All rights reserved.
 * @license      GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;



class PKProjectsControllerProjects extends JControllerAdmin
{
    /**
     * Proxy for getModel.
     *
     * @param     string    $name      The model name. Optional.
     * @param     string    $prefix    The class prefix. Optional.
     * @param     array     $config    The array of possible config values. Optional.
     *
     * @return    jmodel
     */
    public function getModel($name = 'Project', $prefix = 'PKprojectsModel', $config = array('ignore_request' => true))
    {
        $model = parent::getModel($name, $prefix, $config);

        return $model;
    }


    /**
     * Method to copy a list of items.
     *
     * @return    void
     */
    public function copy()
    {
        // Check for request forgeries
        JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));

        $this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false));

        // Get user input and model
        $pks     = JFactory::getApplication()->input->get('cid', array(), 'array');
        $options = JFactory::getApplication()->input->get('copy', array(), 'array');
        $model   = $this->getModel();

        // Make sure the item ids are integers
        JArrayHelper::toInteger($pks);

        // Remove any values of zero.
        $k = array_search(0, $pks, true);

        while ($k !== false)
        {
            unset($pks[$k]);
            $k = array_search(0, $pks, true);
        }

        if (empty($pks)) {
            JLog::add(JText::_('JGLOBAL_NO_ITEM_SELECTED'), JLog::WARNING, 'jerror');
            return;
        }

        // Check access
        if (!PKUserHelper::isSuperAdmin()) {
            $count  = count($pks);
            $user   = JFactory::getUser();
            $levels = PKUserHelper::getAccesslevels();
            $db     = JFactory::getDbo();
            $query  = $db->getQuery(true);

            // Set default options
            if (!isset($options['category_id'])) {
                $options['category_id'] = '';
            }

            if (!isset($options['access'])) {
                $options['access'] = '';
            }

            // Check if the selected access level is allowed
            if (is_numeric($options['access'])) {
                if (!in_array($options['access'], $levels)) {
                    JLog::add(JText::_('PKGLOBAL_ACCESS_LEVEL_NOT_ALLOWED'), JLog::WARNING, 'jerror');
                    return;
                }
            }

            // Check access to the target category
            if (is_numeric($options['category_id'])) {
                $options['category_id'] = (int) $options['category_id'];

                $query->clear()
                      ->select('access')
                      ->from('#__categories')
                      ->where('id = ' . $options['category_id']);

                $db->setQuery($query);
                $cat_access = (int) $db->loadResult();

                // Check viewing access
                if (!in_array($cat_access, $levels)) {
                    JLog::add(JText::_('COM_PKPROJECTS_CATEGORY_ACCESS_DENIED'), JLog::WARNING, 'jerror');
                    return;
                }

                // Check create project permission
                if (!PKUserHelper::authCategory('core.create.project', $options['category_id'])) {
                    JLog::add(JText::_($this->text_prefix . '_CATEGORY_CREATE_PROJECT_DENIED'), JLog::WARNING, 'jerror');
                    return;
                }
            }

            $query->select('id, title, category_id, access, created_by')
                  ->from('#__pk_projects')
                  ->where('id IN(' . implode(',', $pks) . ')');

            $db->setQuery($query);
            $items = $db->loadObjectList('id');

            $can = array();

            for($i = 0; $i != $count; $i++)
            {
                $id  = intval($pks[$i]);
                $pid = $id;

                // Cache permissions
                if (!isset($can[$pid])) {
                    $can[$pid] = array(
                        'edit'     => PKUserHelper::authProject('core.edit', $pid),
                        'edit_own' => (PKUserHelper::authProject('core.edit.own', $pid) && $items[$id]->created_by == $user->id)
                    );
                }

                // Check edit and viewing access
                if (!$can[$pid]['edit'] && !$can[$pid]['edit_own']) {
                    JLog::add(JText::sprintf('PKGLOBAL_ERROR_COPY_ITEM_EDIT_NOT_ALLOWED', $items[$id]->title . ' ' . $pid), JLog::WARNING, 'jerror');
                    unset($pks[$i]);
                    continue;
                }
                elseif (!in_array($items[$id]->access, $levels)) {
                    // If the user has no access, silently remove it from the list
                    unset($pks[$i]);
                    continue;
                }

                if (!is_numeric($options['category_id'])) {
                    // Check access to target category
                    if ($items[$id]->category_id > 0 && !PKUserHelper::authCategory('core.create.project', $items[$id]->category_id)) {
                        unset($pks[$i]);
                    }
                }
            }

            $count = count($pks);

            if (!$count) {
                JLog::add(JText::_('PKGLOBAL_NO_ITEMS_COPIED'), JLog::WARNING, 'jerror');
                return;
            }
        }

        // Copy the items.
        try {
            $model->copy($pks, $options);
            $this->setMessage(JText::plural($this->text_prefix . '_N_ITEMS_COPIED', count($pks)));
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
}
