<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

use PrestaShop\PrestaShop\Core\Util\CacheClearLocker;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

if (!defined('_PS_ADMIN_DIR_')) {
    define('_PS_ADMIN_DIR_', __DIR__);
}

if (!defined('PS_ADMIN_DIR')) {
    define('PS_ADMIN_DIR', _PS_ADMIN_DIR_);
}

require _PS_ADMIN_DIR_ . '/../config/config.inc.php';
define('_PS_APP_ID_', AdminKernel::APP_ID);

//small test to clear cache after upgrade
if (Configuration::get('PS_UPGRADE_CLEAR_CACHE')) {
    header('Cache-Control: max-age=0, must-revalidate');
    header('Expires: Mon, 06 Jun 1985 06:06:00 GMT+1');
    Configuration::updateValue('PS_UPGRADE_CLEAR_CACHE', 0);
}

// Enable APC for autoloading to improve performance.
// You should change the ApcClassLoader first argument to a unique prefix
// in order to prevent cache key conflicts with other applications
// also using APC.
/*
$apcLoader = new ApcClassLoader(sha1(__FILE__), $loader);
$loader->unregister();
$apcLoader->register(true);
*/
if (_PS_MODE_DEV_) {
    Debug::enable();
}
require_once __DIR__ . '/../autoload.php';

// Loads .env file from the root of project
$dotEnvFile = dirname(__FILE__, 2) . '/.env';
(new Dotenv())
    // DO NOT use putEnv
    ->usePutenv(false)
    ->loadEnv($dotEnvFile)
;

// Block the process until the cache clear is in progress, this must be done before the kernel is created so it doesn't
// try to use the old container
CacheClearLocker::waitUntilUnlocked(_PS_ENV_, _PS_APP_ID_);

$kernel = new AdminKernel(_PS_ENV_, _PS_MODE_DEV_);
// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
Request::enableHttpMethodParameterOverride();
$request = Request::createFromGlobals();
Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);

$response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
$response->send();
$kernel->terminate($request, $response);
