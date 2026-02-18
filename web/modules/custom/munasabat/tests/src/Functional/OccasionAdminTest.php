<?php

namespace Drupal\Tests\munasabat\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Occasion admin interface.
 *
 * @group munasabat
 */
class OccasionAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'munasabat',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the admin listing page works.
   */
  public function testAdminListingPage(): void {
    $admin = $this->drupalCreateUser(['administer munasabat']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/munasabat');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Occasions');
    $this->assertSession()->linkExists('Add Occasion');
  }

  /**
   * Tests access control for anonymous users.
   */
  public function testAnonymousAccess(): void {
    $this->drupalGet('/admin/munasabat');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests creating an occasion via the form.
   */
  public function testCreateOccasion(): void {
    $admin = $this->drupalCreateUser(['administer munasabat']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/munasabat/add');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'name[0][value]' => 'Test National Day',
      'type' => 'national_day',
      'date_start[0][value][date]' => '2025-09-23',
      'date_end[0][value][date]' => '2025-09-25',
      'primary_color[0][value]' => '#006B3F',
      'secondary_color[0][value]' => '#FFFFFF',
      'greeting_text[0][value]' => 'Happy National Day!',
    ], 'Save');

    $this->assertSession()->pageTextContains('Occasion Test National Day has been created.');
    $this->assertSession()->addressEquals('/admin/munasabat');
    $this->assertSession()->pageTextContains('Test National Day');
  }

  /**
   * Tests that users without permission cannot access admin pages.
   */
  public function testViewPermission(): void {
    $viewer = $this->drupalCreateUser(['view munasabat']);
    $this->drupalLogin($viewer);

    // Cannot access the listing page.
    $this->drupalGet('/admin/munasabat');
    $this->assertSession()->statusCodeEquals(403);

    // Cannot add occasions.
    $this->drupalGet('/admin/munasabat/add');
    $this->assertSession()->statusCodeEquals(403);
  }

}
