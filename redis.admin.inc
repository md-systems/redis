<?php

/**
 * @file
 * Redis module administration pages.
 */

/**
 * Main settings and review administration screen.
 */
function redis_settings_form($form, &$form_state) {

  $form['connection'] = array(
    '#type' => 'fieldset',
    '#title' => t("Connection information"),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['connection']['scheme'] = array(
    '#type' => 'textfield',
    '#title' => t("Scheme"),
    '#default_value' => 'tcp',
    '#disabled' => TRUE,
    '#description' => t("Connection scheme.") . " " . t("Only <em>tcp</em> is currently supported."),
  );
  $form['connection']['redis_client_host'] = array(
    '#type' => 'textfield',
    '#title' => t("Host"),
    '#default_value' => variable_get('redis_client_host', NULL),
    '#description' => t("Redis server host. Default is <em>@default</em>.", array('@default' => Redis_Client::REDIS_DEFAULT_HOST)),
  );
  $form['connection']['redis_client_port'] = array(
    '#type' => 'textfield',
    '#title' => t("Port"),
    '#default_value' => variable_get('redis_client_port', NULL),
    '#description' => t("Redis server port. Default is <em>@default</em>.", array('@default' => Redis_Client::REDIS_DEFAULT_PORT)),
  );
  $form['connection']['redis_client_base'] = array(
    '#type' => 'textfield',
    '#title' => t("Database"),
    '#default_value' => variable_get('redis_client_base', NULL),
    '#description' => t("Redis server database. Default is none, Redis server will autoselect the database 0."),
  );
  $form['connection']['redis_client_interface'] = array(
    '#type' => 'radios',
    '#title' => t("Client"),
    '#options' => array(
      NULL => t("None or automatic"),
      'PhpRedis' => t("PhpRedis PHP extension"),
      'Predis' => t("Predis PHP library"),
    ),
    '#default_value' => variable_get('redis_client_interface', NULL),
    '#description' => t("Redis low level backend."),
  );

  return system_settings_form($form);
}
