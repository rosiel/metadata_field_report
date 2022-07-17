<?php

namespace Drupal\metadata_field_report\Controller;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\field_report\Controller\FieldReportController;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\system\FileDownloadController;

/**
 * Metadata Field Report Controller.
 *
 * @package Drupal\metadata_field_report\Controller
 */
class MetadataFieldReportController extends FieldReportController {

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * File system interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file download controller.
   *
   * @var \Drupal\system\FileDownloadController
   */
  protected $fileDownloadController;
  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   Entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system interface.
   * @param \Drupal\system\FileDownloadController $fileDownloadController
   */
  public function __construct(EntityFieldManager $entityFieldManager, EntityTypeManager $entityTypeManager, FileSystemInterface $fileSystem, FileDownloadController $fileDownloadController) {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $fileSystem;
    $this->fileDownloadController = $fileDownloadController;
  }

  /**
   * Service injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container object.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      FileDownloadController::create($container)
    );
  }

  /**
   * Return Entities listing and fields.
   *
   * @return array
   *   Returns an array of bundles and theirs fields.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getEntityBundles() {
    $entityList = $this->entityTypeManager->getDefinitions();

    $fieldListings = [];
    foreach ($entityList as $entityKey => $entityValue) {

      // If the Entity has bundle_entity_type set we grab it.
      $bundle_entity_type = $entityValue->get('bundle_entity_type');

      // Check to see if the entity has any bundle before continuing.
      if (!empty($bundle_entity_type)) {
        $entityTypes = $this->entityTypeManager->getStorage($bundle_entity_type)
          ->loadMultiple();

        // Override the Entity Title / Label for select entities.
        switch ($entityKey) {
          case 'block_content':
            $bundleParent = $this->t('Blocks');
            break;

          case 'comment':
            $bundleParent = $this->t('Comments');
            break;

          case 'contact_message':
            $bundleParent = $this->t('Contact Forms');
            break;

          case 'media':
            $bundleParent = $this->t('Media');
            break;

          case 'node':
            $bundleParent = $this->t('Content Types');
            break;

          case 'shortcut':
            $bundleParent = $this->t('Shortcut Menus');
            break;

          case 'taxonomy_term':
            $bundleParent = $this->t('Taxonomy Terms');
            break;

          default:
            $entityLabel = $entityValue->get('label');
            $labelArray = (array) $entityLabel;
            $bundleParent = reset($labelArray);
        }

        // Output the Parent Entity label.
        $fieldListings[] = [
          '#type' => 'markup',
          '#markup' => "<h1 class='fieldReportTable--h1'>" . $bundleParent . "</h1><hr />",
        ];

        foreach ($entityTypes as $entityType) {
          // Load in the entityType fields as rows.
          $rows = $this->getRowsForBundle($entityKey, $entityType->id());

          // Output the entity label.
          $fieldListings[] = [
            '#type' => 'markup',
            '#markup' => "<h2 class='fieldReportTable--h3'>" . $entityType->label() . "</h2>",
          ];

          // Output link to download.
          $fieldListings[] = [
            '#type' => 'link',
            '#title' => $this->t('Download report'),
            '#url' => \Drupal\Core\Url::fromRoute('metadata_field_report.download_bundle_report', ['entityKey'=> $entityKey, 'contentType' => $entityType->id() ]),
          ];

          // Output the entity description.
          $fieldListings[] = [
            '#type' => 'markup',
            '#markup' => "<p>" . $entityType->get('description') . "</p>",
          ];

          $headers = $this->getHeaders();
          // If no rows exist we display a no results message.
          if (!empty($rows)) {
            $fieldListings[] = [
              '#type' => 'table',
              '#header' => $headers,
              '#rows' => $rows,
              '#attributes' => [
                'class' => ['fieldReportTable'],
              ],
              '#attached' => [
                'library' => [
                  'field_report/field-report',
                ],
              ],
            ];
          }
          else {
            $fieldListings[] = [
              '#type' => 'markup',
              '#markup' => $this->t("<p><b>No Fields are avaliable.</b></p>"),
            ];
          }

          // Clear out the rows array to start fresh.
          unset($rows);
        }
      }
    }

    return $fieldListings;
  }

  /**
   * Helper function to get the field definitions.
   *
   * @param string $entityKey
   *   The entity's name.
   * @param string $contentType
   *   The content type name.
   *
   * @return array
   *   Returns an array of the fields.
   */
  public function entityTypeFields($entityKey, $contentType) {

    if (!empty($entityKey) && !empty($contentType)) {
      $fields = array_filter(
        $this->entityFieldManager->getFieldDefinitions($entityKey, $contentType), function ($field_definition) {
          return $field_definition instanceof FieldConfigInterface;
        }
      );
    }

    return $fields;
  }

  /*
   * Helper function to return rows of fields for a given entity type.
   */
  protected function getRowsForBundle($entityKey, $entityType) {
    $fields = $this->entityTypeFields($entityKey, $entityType);

    foreach ($fields as $field => $field_array) {
      $relatedBundles = [];
      $entityOptions = [];
      $targetBundles = ['n/a'];
      $create_new = 'n/a';

      // Get the target bundles configured in Entity Reference fields
      if ($field_array->get('field_type') == 'entity_reference') {
        $targetBundles = $field_array->get('settings')['handler_settings']['target_bundles'];
        $create_new = $field_array->get('settings')['handler_settings']['auto_create'] ? 'TRUE' : 'FALSE';
      }

      // Create the edit field URLs.
      if ($field_array->access('update') && $field_array->hasLinkTemplate("{$field_array->getTargetEntityTypeId()}-field-edit-form")) {
        $editRoute = $field_array->toUrl("{$field_array->getTargetEntityTypeId()}-field-edit-form");
        $entityEdit = Link::fromTextAndUrl('Edit', $editRoute);
        $entityOptions[] = $entityEdit;
      }

      if ($field_array->access('delete') && $field_array->hasLinkTemplate("{$field_array->getTargetEntityTypeId()}-field-delete-form")) {
        // Create the delete field URLs.
        $deleteRoute = $field_array->toUrl("{$field_array->getTargetEntityTypeId()}-field-delete-form");
        $entityDelete = Link::fromTextAndUrl('Delete', $deleteRoute);
        $entityOptions[] = $entityDelete;
      }

      $entityOptionsEditDelete['data']['options']['data'] = [
        '#theme' => 'item_list',
        '#items' => $entityOptions,
        '#context' => ['list_style' => 'comma-list'],
      ];

      $targetBundlesRow['data']['related']['data'] = [
        '#theme' => 'item_list',
        '#items' => $targetBundles,
        '#context' => ['list_style' => 'comma-list'],
      ];

      // Build out our table for the fields.
      $rows[] = [
        $entityKey . "." . $field,
        $field_array->get('label'),
        $field_array->get('field_type'),
        $field_array->get('description'),
        $field_array->get('required') ? 'TRUE' : 'FALSE',
        $field_array->get('translatable') ? 'TRUE' : 'FALSE',
        $targetBundlesRow,
        $create_new,
        $entityOptionsEditDelete,
      ];
    }
    return $rows;
  }

  /*
   * Helper function to return headers.
   */
  protected function getHeaders() {
    return [
      $this->t('Machine name'),
      $this->t('Field Label'),
      $this->t('Field Type'),
      $this->t('Field Description'),
      $this->t('Required'),
      $this->t('Translatable'),
      $this->t('Target bundles'),
      $this->t('Create if does not exist'),
      $this->t('Options'),
    ];
  }

  /**
   * Handler to create a CSV for a specific bundle.
   *
   * @param string $entityKey
   *   The entity's name.
   * @param string $contentType
   *   The content type name.
   *
   * @return array
   *   Returns an array of the fields.
   */
  public function downloadEntityReport($entityKey, $contentType) {
    $headers = $this->getHeaders();
    $rows = $this->getRowsForBundle($entityKey, $contentType);
    // Write to temp directory
    $filename_slug = "{$entityKey}__{$contentType}.csv";
    $filename = $this->fileSystem->getTempDirectory() . '/' . $filename_slug;
    // Write file to filesystem
    if (file_exists($filename)) {
      $this->fileSystem->delete($filename);
    }
    $fh = fopen($filename, 'w');
    fputcsv($fh, $headers);
    foreach ($rows as $row) {
      fputcsv($fh, $row);
    }
    fclose($fh);
    $request = new Request(['file' => $filename_slug]);

    return $this->fileDownloadController->download($request, 'temporary');

  }
}
