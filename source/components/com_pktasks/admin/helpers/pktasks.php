<?php
/**
 * @package      pkg_projectknife
 * @subpackage   com_pktasks
 *
 * @author       Tobias Kuhn (eaxs)
 * @copyright    Copyright (C) 2015-2017 Tobias Kuhn. All rights reserved.
 * @license      GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;


class PKtasksHelper extends JHelperContent
{
    public static $extension = 'com_pktasks';

    /**
     * Configure the Linkbar.
     *
     * @param     string    $view    The name of the active view.
     *
     * @return    void
     */
    public static function addSubmenu($view)
    {
        // Get all PK components
        $db    = JFactory::getDbo();
        $query = $db->getQuery(true);

        $query->select('e.extension_id, e.name, a.admin_view')
              ->from('#__extensions AS e')
              ->join('inner', '#__pk_extensions AS a ON(a.id = e.extension_id)')
              ->where('e.type = ' . $db->quote('component'))
              ->where('e.enabled = 1')
              ->order('a.ordering ASC');

        $db->setQuery($query);
        $items = $db->loadObjectList();

        foreach ($items AS $item)
        {
            JHtmlSidebar::addEntry(
                JText::_(strtoupper($item->name) . '_SUBMENU_' . strtoupper($item->admin_view)),
                'index.php?option=' . $item->name . '&view=' . $item->admin_view,
                $view == $item->admin_view
            );

            if ($item->name == self::$extension) {
                if (version_compare(JVERSION, '3.7', 'ge')) {
                    JHtmlSidebar::addEntry(
                        '&nbsp; ' . JText::_('PKGLOBAL_SUBMENU_CUSTOM_FIELD_GROUPS'),
                        'index.php?option=com_fields&view=groups&context=' . self::$extension . '.task',
                        $view == 'fields.groups'
                    );

                    JHtmlSidebar::addEntry(
                        '&nbsp; ' . JText::_('PKGLOBAL_SUBMENU_CUSTOM_FIELDS'),
                        'index.php?option=com_fields&context=' . self::$extension . '.task',
                        $view == 'fields.fields'
                    );
                }
            }
        }
    }
}
