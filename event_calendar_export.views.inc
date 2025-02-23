<?php

/**
 * @file
 * Views integration for the Event Calendar Export module.
 */

/**
 * Implements hook_views_data().
 */
function event_calendar_export_views_data() {
  $data = [];

  $data['views']['calendar_export_field'] = [
    'title' => t('Calendar Export Links'),
    'help' => t('Provides links to export the event to various calendar formats (ICS, Google Calendar).'),
    'field' => [
      'id' => 'calendar_export_field',
      'title' => t('Calendar Export Links'),
      'help' => t('Provides links to export the event to various calendar formats.'),
    ],
  ];

  return $data;
}
