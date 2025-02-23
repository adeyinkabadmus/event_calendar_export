<?php

namespace Drupal\event_calendar_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for calendar export functionality.
 */
class CalendarExportController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CalendarExportController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Downloads an ICS file for a given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The ICS file response.
   */
  public function downloadIcs($entity_type, $entity_id, Request $request) {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      throw new NotFoundHttpException();
    }

    // Get field names from query parameters
    $start_field = $request->query->get('start');
    $end_field = $request->query->get('end');
    $location_field = $request->query->get('location');
    $description_field = $request->query->get('description');

    if (!$start_field || !$end_field) {
      throw new NotFoundHttpException('Start and end date fields are required');
    }

    // Get field values
    $start_date = $entity->get($start_field)->value;
    $end_date = $entity->get($end_field)->value;
    $title = $entity->label();
    $location = $location_field ? $entity->get($location_field)->value : '';
    $description = $description_field ? $entity->get($description_field)->value : '';

    // Generate ICS content
    $ics = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//Drupal//Event Calendar Export//EN',
      'BEGIN:VEVENT',
      'UID:' . $entity->uuid(),
      'SUMMARY:' . $this->escapeIcsText($title),
      'DTSTART:' . $this->formatIcsDate($start_date),
    ];

    if ($end_date) {
      $ics[] = 'DTEND:' . $this->formatIcsDate($end_date);
    }

    if ($location) {
      $ics[] = 'LOCATION:' . $this->escapeIcsText($location);
    }

    if ($description) {
      $ics[] = 'DESCRIPTION:' . $this->escapeIcsText($description);
    }

    $ics[] = 'END:VEVENT';
    $ics[] = 'END:VCALENDAR';

    $ics_content = implode("\r\n", $ics);

    // Create response
    $response = new Response($ics_content);
    $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s.ics"', 
      preg_replace('/[^a-z0-9-_]/i', '_', $title)
    ));

    return $response;
  }

  /**
   * Format date for ICS.
   */
  protected function formatIcsDate($date) {
    return date('Ymd\THis\Z', strtotime($date));
  }

  /**
   * Escapes special characters in ICS text.
   */
  protected function escapeIcsText($text) {
    $text = str_replace(["\r\n", "\n", "\r"], "\\n", $text);
    $text = str_replace([",", ";", "\\"], ["\\,", "\\;", "\\\\"], $text);
    return $text;
  }
}
