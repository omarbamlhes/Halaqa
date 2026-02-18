<?php

namespace Drupal\Tests\munasabat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\munasabat\Entity\Occasion;
use Drupal\munasabat\Service\OccasionManager;

/**
 * Tests the OccasionManager service.
 *
 * @group munasabat
 */
class OccasionManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'munasabat',
    'image',
    'datetime',
    'file',
    'system',
    'user',
  ];

  /**
   * The occasion manager.
   */
  protected OccasionManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('occasion');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->manager = $this->container->get('munasabat.occasion_manager');
  }

  /**
   * Tests getActiveOccasion() with no active occasions.
   */
  public function testNoActiveOccasion(): void {
    $this->assertNull($this->manager->getActiveOccasion());
    $this->assertFalse($this->manager->isOccasionActive());
  }

  /**
   * Tests getActiveOccasion() with an active occasion.
   */
  public function testActiveOccasion(): void {
    Occasion::create([
      'name' => 'Active Occasion',
      'type' => 'national_day',
      'date_start' => date('Y-m-d'),
      'date_end' => date('Y-m-d', strtotime('+5 days')),
      'is_active' => TRUE,
    ])->save();

    $active = $this->manager->getActiveOccasion();
    $this->assertNotNull($active);
    $this->assertEquals('Active Occasion', $active->getName());
    $this->assertTrue($this->manager->isOccasionActive());
  }

  /**
   * Tests activateBySchedule() activates occasions in date range.
   */
  public function testActivateBySchedule(): void {
    // Occasion within today's date range (should activate).
    Occasion::create([
      'name' => 'Current',
      'type' => 'custom',
      'date_start' => date('Y-m-d', strtotime('-1 day')),
      'date_end' => date('Y-m-d', strtotime('+1 day')),
      'is_active' => FALSE,
    ])->save();

    // Occasion in the past (should stay inactive).
    Occasion::create([
      'name' => 'Past',
      'type' => 'custom',
      'date_start' => date('Y-m-d', strtotime('-10 days')),
      'date_end' => date('Y-m-d', strtotime('-5 days')),
      'is_active' => FALSE,
    ])->save();

    $this->manager->activateBySchedule();

    $storage = \Drupal::entityTypeManager()->getStorage('occasion');

    // Reset static cache.
    $storage->resetCache();

    $occasions = $storage->loadMultiple();
    foreach ($occasions as $occasion) {
      if ($occasion->getName() === 'Current') {
        $this->assertTrue($occasion->isActive());
      }
      if ($occasion->getName() === 'Past') {
        $this->assertFalse($occasion->isActive());
      }
    }
  }

  /**
   * Tests activateBySchedule() deactivates expired occasions.
   */
  public function testDeactivateExpired(): void {
    Occasion::create([
      'name' => 'Expired',
      'type' => 'custom',
      'date_start' => date('Y-m-d', strtotime('-10 days')),
      'date_end' => date('Y-m-d', strtotime('-2 days')),
      'is_active' => TRUE,
    ])->save();

    $this->manager->activateBySchedule();

    $storage = \Drupal::entityTypeManager()->getStorage('occasion');
    $storage->resetCache();

    $occasions = $storage->loadMultiple();
    foreach ($occasions as $occasion) {
      $this->assertFalse($occasion->isActive());
    }
  }

  /**
   * Tests getUpcomingOccasions().
   */
  public function testUpcomingOccasions(): void {
    Occasion::create([
      'name' => 'Future 1',
      'type' => 'ramadan',
      'date_start' => date('Y-m-d', strtotime('+10 days')),
      'date_end' => date('Y-m-d', strtotime('+40 days')),
    ])->save();

    Occasion::create([
      'name' => 'Future 2',
      'type' => 'eid_fitr',
      'date_start' => date('Y-m-d', strtotime('+50 days')),
      'date_end' => date('Y-m-d', strtotime('+53 days')),
    ])->save();

    $upcoming = $this->manager->getUpcomingOccasions();
    $this->assertCount(2, $upcoming);

    $next = $this->manager->getNextOccasion();
    $this->assertEquals('Future 1', $next->getName());
  }

  /**
   * Tests hex color validation.
   */
  public function testColorValidation(): void {
    $this->assertTrue(OccasionManager::isValidHexColor('#006B3F'));
    $this->assertTrue(OccasionManager::isValidHexColor('#ffffff'));
    $this->assertTrue(OccasionManager::isValidHexColor('#ABCDEF'));
    $this->assertTrue(OccasionManager::isValidHexColor(NULL));
    $this->assertTrue(OccasionManager::isValidHexColor(''));
    $this->assertFalse(OccasionManager::isValidHexColor('red'));
    $this->assertFalse(OccasionManager::isValidHexColor('#FFF'));
    $this->assertFalse(OccasionManager::isValidHexColor('006B3F'));
    $this->assertFalse(OccasionManager::isValidHexColor('#006B3F; background: url(evil)'));
  }

}
