<?php

namespace Drupal\Tests\migrate_sandbox\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that access to the sandbox is configured correctly.
 *
 * @group migrate_sandbox
 */
class MigrateSandboxAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_sandbox',
    'user',
  ];

  /**
   * Test that the permission and route are defined securely.
   */
  public function testMigrateSandboxAccess() {
    $permission_name = 'access migrate_sandbox';

    // Check that permission is required on the route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = \Drupal::service('router.route_provider')->getRouteByName('migrate_sandbox.settings_form');
    $permission = $route->getRequirement('_permission');
    $this->assertSame($permission_name, $permission);

    // Check that the permission is marked as security risk.
    $permissions = \Drupal::service('user.permissions')->getPermissions();
    $this->assertTrue($permissions[$permission_name]['restrict access']);
  }

}
