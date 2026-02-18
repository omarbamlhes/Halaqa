<?php

namespace Drupal\Tests\munasabat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\munasabat\Entity\Occasion;

/**
 * Tests the Occasion entity CRUD operations.
 *
 * @group munasabat
 */
class OccasionEntityTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('occasion');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
  }

  /**
   * Tests creating an occasion entity.
   */
  public function testCreateOccasion(): void {
    $occasion = Occasion::create([
      'name' => 'National Day',
      'type' => 'national_day',
      'date_start' => '2025-09-23',
      'date_end' => '2025-09-25',
      'is_active' => FALSE,
      'primary_color' => '#006B3F',
      'secondary_color' => '#FFFFFF',
      'css_theme' => 'national_day',
      'effects' => 'confetti',
      'greeting_text' => 'Happy National Day!',
    ]);
    $occasion->save();

    $this->assertNotNull($occasion->id());
    $this->assertEquals('National Day', $occasion->getName());
    $this->assertEquals('national_day', $occasion->getType());
    $this->assertEquals('2025-09-23', $occasion->getStartDate());
    $this->assertEquals('2025-09-25', $occasion->getEndDate());
    $this->assertFalse($occasion->isActive());
    $this->assertEquals('#006B3F', $occasion->getPrimaryColor());
    $this->assertEquals('#FFFFFF', $occasion->getSecondaryColor());
    $this->assertEquals('national_day', $occasion->getCssTheme());
    $this->assertEquals('confetti', $occasion->getEffects());
    $this->assertEquals('Happy National Day!', $occasion->getGreetingText());
  }

  /**
   * Tests toggling the active status.
   */
  public function testActiveToggle(): void {
    $occasion = Occasion::create([
      'name' => 'Test Occasion',
      'type' => 'custom',
      'date_start' => '2025-01-01',
      'date_end' => '2025-01-05',
      'is_active' => FALSE,
    ]);
    $occasion->save();

    $this->assertFalse($occasion->isActive());

    $occasion->setActive(TRUE);
    $occasion->save();

    $loaded = Occasion::load($occasion->id());
    $this->assertTrue($loaded->isActive());
  }

  /**
   * Tests deleting an occasion.
   */
  public function testDeleteOccasion(): void {
    $occasion = Occasion::create([
      'name' => 'To Delete',
      'type' => 'custom',
      'date_start' => '2025-01-01',
      'date_end' => '2025-01-05',
    ]);
    $occasion->save();
    $id = $occasion->id();

    $occasion->delete();
    $this->assertNull(Occasion::load($id));
  }

}
