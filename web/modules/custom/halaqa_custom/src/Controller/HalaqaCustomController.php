<?php

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\halaqa_custom\Service\HalaqaStudentService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Halaqa Custom module.
 */
class HalaqaCustomController extends ControllerBase {

  /**
   * The Halaqa student service.
   *
   * @var \Drupal\halaqa_custom\Service\HalaqaStudentService
   */
  protected HalaqaStudentService $halaqaStudentService;

  /**
   * Constructs a HalaqaCustomController object.
   *
   * @param \Drupal\halaqa_custom\Service\HalaqaStudentService $halaqa_student_service
   *   The Halaqa student service.
   */
  public function __construct(HalaqaStudentService $halaqa_student_service) {
    $this->halaqaStudentService = $halaqa_student_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('halaqa_custom.student_service')
    );
  }

  /**
   * Example page callback.
   *
   * @return array
   *   A render array.
   */
  public function example(): array {
    return [
      '#markup' => $this->t('Welcome to Halaqa Custom module!'),
    ];
  }

  /**
   * Display students for a specific Halaqa.
   *
   * @param int $nid
   *   The Halaqa node ID.
   *
   * @return array
   *   A render array.
   */
  public function students(int $nid): array {
    // Load the Halaqa.
    $halaqa = $this->halaqaStudentService->loadHalaqa($nid);

    if (!$halaqa) {
      throw new NotFoundHttpException($this->t('Halaqa not found.'));
    }

    // Get students for this Halaqa.
    $students = $this->halaqaStudentService->getStudentsByHalaqa($nid);

    $build = [
      '#theme' => 'halaqa_students_list',
      '#halaqa' => $halaqa,
      '#students' => $students,
      '#cache' => [
        'tags' => ['node:' . $nid, 'node_list:student'],
        'contexts' => ['user'],
      ],
    ];

    // Fallback if theme not yet available.
    if (empty($students)) {
      $build = [
        '#markup' => $this->t('No students found in this Halaqa.'),
        '#cache' => [
          'tags' => ['node:' . $nid, 'node_list:student'],
          'contexts' => ['user'],
        ],
      ];
    }
    else {
      // Build a simple list.
      $items = [];
      foreach ($students as $student) {
        $items[] = [
          '#markup' => $student->label(),
        ];
      }

      $build = [
        '#theme' => 'item_list',
        '#title' => $this->t('Students in @halaqa', ['@halaqa' => $halaqa->label()]),
        '#items' => $items,
        '#list_type' => 'ul',
        '#cache' => [
          'tags' => ['node:' . $nid, 'node_list:student'],
          'contexts' => ['user'],
        ],
      ];
    }

    return $build;
  }

  /**
   * Title callback for the students page.
   *
   * @param int $nid
   *   The Halaqa node ID.
   *
   * @return string
   *   The page title.
   */
  public function studentsTitle(int $nid): string {
    $halaqa = $this->halaqaStudentService->loadHalaqa($nid);

    if ($halaqa) {
      return $this->t('Students - @halaqa', ['@halaqa' => $halaqa->label()]);
    }

    return $this->t('Students');
  }

  /**
   * Display all Halaqas for the current teacher.
   *
   * @return array
   *   A render array.
   */
  public function myHalaqas(): array {
    $halaqas = $this->halaqaStudentService->getHalaqasByCurrentTeacher();

    if (empty($halaqas)) {
      return [
        '#markup' => $this->t('You have no Halaqas assigned.'),
      ];
    }

    $items = [];
    foreach ($halaqas as $halaqa) {
      $items[] = [
        '#type' => 'link',
        '#title' => $halaqa->label(),
        '#url' => $halaqa->toUrl(),
        '#suffix' => ' | ',
        'students_link' => [
          '#type' => 'link',
          '#title' => $this->t('View Students'),
          '#url' => \Drupal\Core\Url::fromRoute('halaqa_custom.halaqa_students', ['nid' => $halaqa->id()]),
        ],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('My Halaqas'),
      '#items' => $items,
      '#list_type' => 'ul',
      '#cache' => [
        'tags' => ['node_list:halaqa'],
        'contexts' => ['user'],
      ],
    ];
  }

}
