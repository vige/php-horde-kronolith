<?php
/**
 * Setup autoloading for the tests.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Kronolith
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/kronolith
 * @license    http://www.horde.org/licenses/gpl GNU General Public License, version 2
 */

Horde_Test_Autoload::addPrefix('Kronolith', __DIR__ . '/../../lib');

/** Load the basic test definition */
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/Integration/Driver/Base.php';
require_once __DIR__ . '/Integration/Driver/Sql/Base.php';
require_once __DIR__ . '/Integration/Kronolith/Base.php';
require_once __DIR__ . '/Integration/Kronolith/Sql/Base.php';

/** Load stub definitions */
require_once __DIR__ . '/Stub/Driver.php';
require_once __DIR__ . '/Stub/Registry.php';
require_once __DIR__ . '/Stub/ShareFactory.php';
require_once __DIR__ . '/Stub/Tagger.php';
require_once __DIR__ . '/Stub/Types.php';
require_once __DIR__ . '/Stub/CalendarManager.php';
require_once __DIR__ . '/Stub/ObjectsManager.php';
