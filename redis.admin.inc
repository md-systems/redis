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
  $form['connection']['host'] = array(
    '#type' => 'textfield',
    '#title' => t("Host"),
    '#description' => t("Redis server host. Default is <em>@default</em>.", array('@default' => Redis_Client::REDIS_DEFAULT_HOST)),
  );
  $form['connection']['port'] = array(
    '#type' => 'textfield',
    '#title' => t("Port"),
    '#description' => t("Redis server port. Default is <em>@default</em>.", array('@default' => Redis_Client::REDIS_DEFAULT_PORT)),
  );
  $form['connection']['base'] = array(
    '#type' => 'textfield',
    '#title' => t("Database"),
    '#description' => t("Redis server database. Default is none, Redis server will autoselect the database 0."),
  );

  return system_settings_form($form);
}
