<?php

namespace Drupal\event_calendar_export\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present calendar export links.
 *
 * @ViewsField("calendar_export_field")
 */
class CalendarExportField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query needed for this field.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['start_date_field'] = ['default' => ''];
    $options['end_date_field'] = ['default' => ''];
    $options['title_field'] = ['default' => ''];
    $options['location_field'] = ['default' => ''];
    $options['description_field'] = ['default' => ''];
    $options['show_ics'] = ['default' => TRUE];
    $options['show_google'] = ['default' => TRUE];
    $options['ics_text'] = ['default' => $this->t('Download ICS')];
    $options['google_text'] = ['default' => $this->t('Add to Google Calendar')];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $field_options = $this->getFieldOptions();

    $form['field_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Field Mapping'),
      '#open' => TRUE,
      '#weight' => -5,
    ];

    $form['field_mapping']['start_date_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Start Date Field'),
      '#options' => $field_options,
      '#default_value' => $this->options['start_date_field'],
      '#required' => TRUE,
      '#description' => $this->t('Select the field containing the event start date.'),
    ];

    $form['field_mapping']['end_date_field'] = [
      '#type' => 'select',
      '#title' => $this->t('End Date Field'),
      '#options' => $field_options,
      '#default_value' => $this->options['end_date_field'],
      '#required' => TRUE,
      '#description' => $this->t('Select the field containing the event end date.'),
    ];

    $form['field_mapping']['title_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Field'),
      '#options' => $field_options,
      '#default_value' => $this->options['title_field'],
      '#required' => TRUE,
      '#description' => $this->t('Select the field containing the event title.'),
    ];

    $form['field_mapping']['location_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Location Field'),
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['location_field'],
      '#description' => $this->t('Optional: Select the field containing the event location.'),
    ];

    $form['field_mapping']['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description Field'),
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->options['description_field'],
      '#description' => $this->t('Optional: Select the field containing the event description.'),
    ];

    $form['display_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Display Options'),
      '#open' => TRUE,
      '#weight' => -4,
    ];

    $form['display_options']['show_ics'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show ICS download link'),
      '#default_value' => $this->options['show_ics'],
    ];

    $form['display_options']['ics_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ICS link text'),
      '#default_value' => $this->options['ics_text'],
      '#states' => [
        'visible' => [
          ':input[name="options[display_options][show_ics]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['display_options']['show_google'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Google Calendar link'),
      '#default_value' => $this->options['show_google'],
    ];

    $form['display_options']['google_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Calendar link text'),
      '#default_value' => $this->options['google_text'],
      '#states' => [
        'visible' => [
          ':input[name="options[display_options][show_google]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    
    // Get values from the nested structure
    $values = $form_state->getValue(['options']);
    
    // Update field mappings
    $this->options['start_date_field'] = $values['field_mapping']['start_date_field'];
    $this->options['end_date_field'] = $values['field_mapping']['end_date_field'];
    $this->options['title_field'] = $values['field_mapping']['title_field'];
    $this->options['location_field'] = $values['field_mapping']['location_field'];
    $this->options['description_field'] = $values['field_mapping']['description_field'];
    
    // Update display options
    $this->options['show_ics'] = $values['display_options']['show_ics'];
    $this->options['show_google'] = $values['display_options']['show_google'];
    $this->options['ics_text'] = $values['display_options']['ics_text'];
    $this->options['google_text'] = $values['display_options']['google_text'];
  }

  /**
   * Get available fields for the view.
   */
  protected function getFieldOptions() {
    $options = [];
    $handlers = $this->view->display_handler->getHandlers('field');
    
    foreach ($handlers as $field_id => $field) {
      // Skip our own field to prevent recursion
      if ($field instanceof CalendarExportField) {
        continue;
      }
      $options[$field_id] = $field->adminLabel();
    }
    
    return $options;
  }

  /**
   * Gets the value of a field from the result row.
   */
  protected function getFieldValue(ResultRow $row, $field_name) {
    if (empty($field_name) || !isset($this->view->field[$field_name])) {
      return '';
    }

    $field = $this->view->field[$field_name];
    try {
      $value = $field->getValue($row);
      
      // If it's an array (like from a datetime field), get the value
      if (is_array($value)) {
        $value = reset($value);
      }
      
      // If we still don't have a value, try getting the rendered output
      if (empty($value)) {
        $rendered = $field->render($row);
        if (is_array($rendered)) {
          // Convert render array to string if needed
          $value = \Drupal::service('renderer')->renderPlain($rendered);
        } else {
          $value = $rendered;
        }
      }
      
      return $value;
    }
    catch (\Exception $e) {
      \Drupal::logger('event_calendar_export')->error('Error getting field value: @error', ['@error' => $e->getMessage()]);
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    
    // Get field values for preview
    $start_date = $this->getFieldValue($values, $this->options['start_date_field']);
    $end_date = $this->getFieldValue($values, $this->options['end_date_field']);
    $title = $this->getFieldValue($values, $this->options['title_field']);
    $location = $this->getFieldValue($values, $this->options['location_field']);
    $description = $this->getFieldValue($values, $this->options['description_field']);

    $links = [
      '#theme' => 'calendar_export_links',
      '#ics_url' => NULL,
      '#google_url' => NULL,
      '#show_ics' => $this->options['show_ics'],
      '#show_google' => $this->options['show_google'],
      '#ics_text' => $this->options['ics_text'],
      '#google_text' => $this->options['google_text'],
    ];

    // Only generate URLs for enabled options
    if ($this->options['show_ics']) {
      $route_params = [
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
      ];
      
      $query = [
        'start' => $this->options['start_date_field'],
        'end' => $this->options['end_date_field'],
      ];
      
      if (!empty($this->options['location_field'])) {
        $query['location'] = $this->options['location_field'];
      }
      
      if (!empty($this->options['description_field'])) {
        $query['description'] = $this->options['description_field'];
      }

      $links['#ics_url'] = Url::fromRoute('event_calendar_export.ics_download', $route_params, [
        'query' => $query,
      ])->toString();
    }

    if ($this->options['show_google']) {
      $links['#google_url'] = $this->buildGoogleCalendarUrl($title, $start_date, $end_date, $location, $description);
    }

    return $links;
  }

  /**
   * Build Google Calendar URL.
   */
  protected function buildGoogleCalendarUrl($title, $start_date, $end_date, $location, $description) {
    $params = [
      'text' => $title,
      'dates' => $this->formatGoogleCalendarDate($start_date) . '/' . $this->formatGoogleCalendarDate($end_date),
    ];

    if ($location) {
      $params['location'] = $location;
    }

    if ($description) {
      $params['details'] = $description;
    }

    return Url::fromUri('https://calendar.google.com/calendar/render', [
      'query' => [
        'action' => 'TEMPLATE',
      ] + array_filter($params),
    ])->toString();
  }

  /**
   * Format date for Google Calendar URL.
   */
  protected function formatGoogleCalendarDate($date) {
    if (is_string($date)) {
      $date = strtotime($date);
    }
    return date('Ymd\THis\Z', $date);
  }
}
