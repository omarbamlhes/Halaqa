<?php

declare(strict_types=1);

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;

use Drupal\halaqa_custom\Service\HalaqaStudentService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for user profile pages.
 */
class ProfileController extends ControllerBase {

  /**
   * The Halaqa student service.
   *
   * @var \Drupal\halaqa_custom\Service\HalaqaStudentService
   */
  protected HalaqaStudentService $halaqaService;

  /**
   * Constructs a ProfileController object.
   *
   * @param \Drupal\halaqa_custom\Service\HalaqaStudentService $halaqa_service
   *   The Halaqa student service.
   */
  public function __construct(HalaqaStudentService $halaqa_service) {
    $this->halaqaService = $halaqa_service;
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
   * Displays the user profile page.
   *
   * @return array
   *   A render array.
   */
  public function userProfile(): array {
    $user = User::load($this->currentUser()->id());
    if (!$user) {
      return [
        '#markup' => $this->t('User not found.'),
      ];
    }

    // Get user data.
    $user_data = $this->getUserProfileData($user);
    
    // Get role-specific data.
    $role_data = $this->getRoleSpecificData($user);

    $build = [
      '#theme' => 'halaqa_user_profile',
      '#user_data' => $user_data,
      '#role_data' => $role_data,
      '#attached' => [
        'library' => [
          'halaqa_custom/profile_styles',
        ],
      ],
      '#cache' => [
        'tags' => ['user:' . $user->id()],
        'contexts' => ['user'],
      ],
    ];

    return $build;
  }

  /**
   * Edit profile page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function editProfile() {
    // Redirect to Drupal's user edit form.
    $user = User::load($this->currentUser()->id());
    
    return $this->redirect('entity.user.edit_form', ['user' => $user->id()]);
  }

  /**
   * Get user profile data.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   User profile data.
   */
  protected function getUserProfileData(User $user): array {
    // Get user picture.
    $picture_url = '';
    if ($user->hasField('user_picture') && !$user->get('user_picture')->isEmpty()) {
      $file = $user->get('user_picture')->entity;
      if ($file) {
        $picture_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      }
    }

    // Get roles.
    $roles = $user->getRoles(TRUE);
    $role_labels = [];
    foreach ($roles as $role_id) {
      $role = \Drupal::entityTypeManager()->getStorage('user_role')->load($role_id);
      if ($role) {
        $role_labels[] = $role->label();
      }
    }

    // Calculate account age.
    $created = (int) $user->getCreatedTime();
    $account_age = $this->calculateAccountAge($created);

    // Last access.
    $last_access = $user->getLastAccessedTime();
    $last_access_formatted = $last_access > 0 
      ? \Drupal::service('date.formatter')->format($last_access, 'medium')
      : $this->t('Never');

    return [
      'uid' => $user->id(),
      'name' => $user->getDisplayName(),
      'email' => $user->getEmail(),
      'picture_url' => $picture_url,
      'roles' => $role_labels,
      'role_ids' => $roles,
      'created' => \Drupal::service('date.formatter')->format($created, 'medium'),
      'account_age' => $account_age,
      'last_access' => $last_access_formatted,
      'status' => $user->isActive() ? $this->t('Active') : $this->t('Blocked'),
      'timezone' => $user->getTimeZone() ?: \Drupal::config('system.date')->get('timezone.default'),
      'language' => $user->getPreferredLangcode(),
    ];
  }

  /**
   * Get role-specific data.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   Role-specific data.
   */
  protected function getRoleSpecificData(User $user): array {
    $roles = $user->getRoles();
    $data = [
      'type' => 'user',
      'stats' => [],
      'recent_activity' => [],
    ];

    // Teacher data.
    if (in_array('teacher', $roles)) {
      $data['type'] = 'teacher';
      $stats = $this->halaqaService->getTeacherDashboardStats();
      
      $data['stats'] = [
        [
          'icon' => '🕌',
          'label' => $this->t('Halaqas'),
          'value' => $stats['halaqa_count'] ?? 0,
          'color' => '#00d4aa',
        ],
        [
          'icon' => '👨‍🎓',
          'label' => $this->t('Students'),
          'value' => $stats['total_students'] ?? 0,
          'color' => '#667eea',
        ],
        [
          'icon' => '📝',
          'label' => $this->t('Sessions'),
          'value' => count($stats['recent_records'] ?? []),
          'color' => '#ffd700',
        ],
        [
          'icon' => '⭐',
          'label' => $this->t('Excellent'),
          'value' => 0,
          'color' => '#ff6b6b',
        ],
      ];

      // Recent activity.
      $records = $stats['recent_records'] ?? [];
      foreach ($records as $record) {
        if ($record instanceof \Drupal\node\NodeInterface) {
          $student_name = '';
          if ($record->hasField('field_student') && !$record->get('field_student')->isEmpty()) {
            $student = $record->get('field_student')->entity;
            $student_name = $student ? $student->label() : '';
          }
          $evaluation = '';
          if ($record->hasField('field_evaluation') && !$record->get('field_evaluation')->isEmpty()) {
            $evaluation = $record->get('field_evaluation')->value;
          }
          $data['recent_activity'][] = [
            'type' => 'record',
            'title' => $student_name ?: $this->t('Student'),
            'description' => $this->t('Memorization session - @eval', ['@eval' => $evaluation]),
            'date' => \Drupal::service('date.formatter')->format($record->getCreatedTime(), 'short'),
            'icon' => '📖',
          ];
        }
      }
    }

    // Student data.
    elseif (in_array('student', $roles)) {
      $data['type'] = 'student';
      
      // Get student node.
      $student_nodes = $this->halaqaService->getStudentNodesByUser((int) $user->id());
      if (!empty($student_nodes)) {
        $student_nid = reset($student_nodes);
        $progress = $this->halaqaService->getStudentProgress($student_nid);
        
        $data['stats'] = [
          [
            'icon' => '📚',
            'label' => $this->t('Total Sessions'),
            'value' => $progress['total_records'] ?? 0,
            'color' => '#00d4aa',
          ],
          [
            'icon' => '📖',
            'label' => $this->t('Ayahs Memorized'),
            'value' => $progress['total_ayahs'] ?? 0,
            'color' => '#667eea',
          ],
          [
            'icon' => '🕌',
            'label' => $this->t('Surahs Covered'),
            'value' => $progress['surahs_count'] ?? 0,
            'color' => '#ffd700',
          ],
          [
            'icon' => '⭐',
            'label' => $this->t('Excellent'),
            'value' => $progress['evaluations']['excellent'] ?? 0,
            'color' => '#ff6b6b',
          ],
        ];

        // Halaqa info.
        $halaqa_info = $this->halaqaService->getStudentHalaqaAndTeacher($student_nid);
        if ($halaqa_info) {
          $data['halaqa'] = $halaqa_info;
        }
      }
    }

    // Parent data.
    elseif (in_array('parent', $roles)) {
      $data['type'] = 'parent';
      $stats = $this->halaqaService->getParentDashboardStats();
      
      $total_ayahs = 0;
      $excellent_count = 0;
      $children_formatted = [];
      
      foreach ($stats['children'] ?? [] as $child_data) {
        $progress = $child_data['progress'] ?? [];
        $total_ayahs += $progress['total_ayahs'] ?? 0;
        $excellent_count += $progress['evaluations']['excellent'] ?? 0;
        
        $student = $child_data['student'] ?? NULL;
        if ($student instanceof \Drupal\node\NodeInterface) {
          $children_formatted[] = [
            'nid' => $student->id(),
            'name' => $student->label(),
            'halaqa_name' => $child_data['halaqa_name'] ?? '',
          ];
        }
      }
      
      $data['stats'] = [
        [
          'icon' => '👨‍👩‍👧‍👦',
          'label' => $this->t('Children'),
          'value' => $stats['children_count'] ?? 0,
          'color' => '#00d4aa',
        ],
        [
          'icon' => '📚',
          'label' => $this->t('Total Sessions'),
          'value' => $stats['total_records'] ?? 0,
          'color' => '#667eea',
        ],
        [
          'icon' => '📖',
          'label' => $this->t('Total Ayahs'),
          'value' => $total_ayahs,
          'color' => '#ffd700',
        ],
        [
          'icon' => '⭐',
          'label' => $this->t('Excellent'),
          'value' => $excellent_count,
          'color' => '#ff6b6b',
        ],
      ];

      $data['children'] = $children_formatted;
    }

    return $data;
  }

  /**
   * Calculate account age.
   *
   * @param int $created
   *   The created timestamp.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   Human-readable account age.
   */
  protected function calculateAccountAge(int $created) {
    $now = \Drupal::time()->getCurrentTime();
    $diff = $now - $created;
    
    $days = floor($diff / 86400);
    
    if ($days < 1) {
      return $this->t('Today');
    }
    elseif ($days == 1) {
      return $this->t('1 day');
    }
    elseif ($days < 30) {
      return $this->t('@days days', ['@days' => $days]);
    }
    elseif ($days < 365) {
      $months = floor($days / 30);
      return $months == 1 
        ? $this->t('1 month')
        : $this->t('@months months', ['@months' => $months]);
    }
    else {
      $years = floor($days / 365);
      return $years == 1 
        ? $this->t('1 year')
        : $this->t('@years years', ['@years' => $years]);
    }
  }

}
