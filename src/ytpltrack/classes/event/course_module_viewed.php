<?php
/**
 * Defines the view event.
 *
 * @package    mod_ytpltrack
 * @copyright  2017 Henry Groover <henry.groover@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

namespace mod_ytpltrack\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_ytpltrack instance viewed event class
 *
 * If the view mode needs to be stored as well, you may need to
 * override methods get_url() and get_legacy_log_data(), too.
 *
 * @package    mod_ytpltrack
 * @copyright  2017 Henry Groover <henry.groover@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['objecttable'] = 'ytpltrack';
        parent::init();
    }
}
