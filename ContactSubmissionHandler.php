<?php

namespace Drupal\pfe_med_connect\Plugin\ContactSubmissionHandler;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\contact\Plugin\ContactSubmissionInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\pfe_med_connect\Form\CustomForm;
use Drupal\Core\Database\Connection;

/**
 * Provides a PFE Med Connect submission handler.
 *
 * @ContactSubmissionHandler(
 *   id = "pfe_med_connect",
 *   label = @Translation("PFE Med Connect Submission Handler"),
 *   category = @Translation("Form Handler"),
 *   deriver = "Drupal\pfe_med_connect\Plugin\Derivative\PfeMedConnectSubmissionHandler",
 * )
 */
class PfeMedConnectSubmissionHandler extends PluginBase implements ContainerFactoryPluginInterface {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new PfeMedConnectSubmissionHandler object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MailManagerInterface $mail_manager, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailManager = $mail_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.mail'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contact_message_feedback_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, ContactSubmissionInterface $contact_submission) {
    // Get the submitted form values.
    $values = $form_state->getValues();

    // Get the selected product and therapeutic area.
    $product = $values['produit'];
    $therapeuticArea = $values['aire_therapeutique'];

    // Get the department value if available.
    $department = isset($values['department']) ? $values['department'] : '';

    // Query the custom_table to get the email addresses.
    $emailAddresses = $this->getEmailAddresses($product, $therapeuticArea, $department);

    // Prepare the email parameters.
    $params = [
      'message' => $values['message'],
    ];

    // Check if there are any email addresses.
    if (!empty($emailAddresses)) {
      // Send email to RMR and backup email addresses.
      $this->sendEmail($params, $emailAddresses['RMR_adresse_email'], $emailAddresses['Backup_adresse_email']);
    } else {
      // Send a different email if no email addresses are found.
      $this->sendDifferentEmail($params);
    }
  }

  /**
   * Queries the custom_table to get the email addresses.
   *
   * @param string $product
   *   The product value.
   * @param string $therapeuticArea
   *   The therapeutic area value.
   * @param string $department
   *   The department value.
   *
   * @return array
   *   An array of email addresses.
   */
  protected function getEmailAddresses($product, $therapeuticArea, $department) {
    $query = $this->database->select('custom_table', 'ct')
      ->fields('ct', ['RMR_adresse_email', 'Backup_adresse_email'])
      ->condition('Produit', $product)
      ->condition('Aire_therapeutique', $therapeuticArea);

    // Add the department condition if it exists.
    if (!empty($department)) {
      $query->condition('Departement', $department);
    } else {
      $query->isNull('Departement');
    }

    $result = $query->execute()->fetchAssoc();

    return $result ? $result : [];
  }

  /**
   * Sends the email to the RMR and backup email addresses.
   *
   * @param array $params
   *   An array of email parameters.
   * @param string $rmr_email
   *   The RMR email address.
   * @param string $backup_email
   *   The backup email address.
   */
  protected function sendEmail(array $params, $rmr_email, $backup_email) {
    $mail_params = [
      'message' => $params['message'],
    ];

    // Send email to RMR.
    $this->mailManager->mail(
      'pfe_med_connect',
      'contact_submission_rmr',
      $rmr_email,
      'en',
      $mail_params,
      NULL,
      TRUE
    );

    // Send email to backup.
    $this->mailManager->mail(
      'pfe_med_connect',
      'contact_submission_backup',
      $backup_email,
      'en',
      $mail_params,
      NULL,
      TRUE
    );
  }

  /**
   * Sends a different email to a specific recipient.
   *
   * @param array $params
   *   An array of email parameters.
   */
  protected function sendDifferentEmail(array $params) {
    $mail_params = [
      'message' => $params['message'],
    ];

    // Send email to a different recipient.
    $this->mailManager->mail(
      'pfe_med_connect',
      'contact_submission_different',
      'recipient@example.com',
      'en',
      $mail_params,
      NULL,
      TRUE
    );
  }

}
