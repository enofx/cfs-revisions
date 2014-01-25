<?php
/*
Plugin Name: CFS - Revisions
Plugin URI: https://uproot.us/addons/revisions/
Description: Revisions support for Custom Field Suite (add-on).
Version: 1.2
Author: Matt Gibbs
Author URI: https://uproot.us/

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <http://www.gnu.org/licenses/>.
*/

$cfs_revisions_addon = new cfs_revisions_addon();

class cfs_revisions_addon
{
    public $version;

    function __construct()
    {
        $this->version = '1.2';

        // plugin updater
        include(dirname(__FILE__) . '/updater.php');
        $updater = new cfs_plugin_updater('cfs-revisions', $this->version);

        add_action('save_post', array($this, 'save_post'));
        add_action('wp_restore_post_revision', array($this, 'wp_restore_post_revision'), 10, 2);
        add_action('wp_delete_post_revision', array($this, 'wp_delete_post_revision'));

        add_filter('_wp_post_revision_fields', array($this, '_wp_post_revision_fields'));
        add_filter('_wp_post_revision_field_cfs_postmeta', array($this, '_wp_post_revision_field_postmeta'), 10, 3 );
        add_filter('wp_save_post_revision_check_for_changes', array($this, 'check_for_changes'), 10, 3);
    }




    // wp-includes/revision.php - wp_save_post_revision()
    function _wp_post_revision_fields($fields)
    {
        $fields[ 'cfs_postmeta' ] = __('Post Meta');
        return $fields;
    }




    // wp-admin/includes/ajax-actions - wp_ajax_revisions_data()
    function _wp_post_revision_field_postmeta($value = '', $column = 'cfs_postmeta', $post)
    {
        global $cfs;

        $output = '';
        $fields = $cfs->get(false, $post->ID);
        $field_info = $cfs->get_field_info(false, $post->ID);

        foreach ($fields as $field_name => $field_data) {
            $output .= '[' . $field_name . "]\n";

            if (is_array($field_data)) {
                $props = $field_info[$field_name];
                if ('relationship' == $props['type']) {
                    $values = array();
                    if (!empty($field_data)) {
                        foreach ($field_data as $item_id) {
                            $values[] = get_post($item_id)->post_title;
                        }
                    }
                    $output .= json_encode($values) . "\n";
                }
                else {
                    $output .= json_encode($field_data) . "\n";
                }
            }
            else {
                $output .= $field_data . "\n";
            }
        }
        return $output;
    }




    // wp-includes/revision.php -> wp_save_post_revision()
    function check_for_changes($default = true, $last_revision, $post)
    {
        global $cfs;

        $revision_data = $cfs->get(false, $last_revision->ID);
        $post_data = $cfs->get(false, $post->ID);

        if (serialize($revision_data) != serialize($post_data)) {
            return false;
        }

        return true;
    }




    function save_post($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $original_post_id = wp_is_post_revision($post_id);

        if ($original_post_id) {
            global $cfs;

            // Copy custom fields to the revision
            $field_data = $cfs->get(false, $original_post_id, array('format' => 'raw'));
            $cfs->save($field_data, array('ID' => $post_id));
        }
    }




    // wp-includes/revision.php -> wp_restore_post_revision()
    function wp_restore_post_revision($post_id, $revision_id)
    {
        global $cfs;

        $field_data = $cfs->get(false, $revision_id, array('format' => 'raw'));
        $cfs->save($field_data, array('ID' => $post_id));
    }




    // wp-includes/revision.php -> wp_delete_post_revision()
    function wp_delete_post_revision($revision_id)
    {
        global $wpdb;

        $revision_id = (int) $revision_id;
        $wpdb->query("DELETE FROM {$wpdb->prefix}cfs_values WHERE post_id = $revision_id");
    }
}
