<?php

/**
 * Autoload callback.
 *
 * @param string $class
 */
function _drux_autoload($class) {
  if (1
    && 'drux_' === substr($class, 0, 5)
    && FALSE === strpos($class, '\\')
  ) {
    require_once __DIR__ . '/lib/' . str_replace('_', '/', substr($class, 5)) . '.php';
  }
}

if (!function_exists('xautoload')) {
  spl_autoload_register('_drux_autoload');
}

/**
 * Implements hook_drush_command().
 */
function drux_drush_command() {
  $items['drux-enable-dependencies'] = array(
    'description' => 'Download and enable dependencies of existing modules.',
    'aliases' => array('dep-en'),
  );
  $items['drux-list-dependencies'] = array(
    'description' => 'Download and enable dependencies of existing modules.',
    'aliases' => array('dep'),
  );
  $items['drux-find-obsolete'] = array(
    'description' => 'Find modules that are not direct or indirect dependencies of the modules given as arguments',
    'arguments' => array(
      'modules' => 'List of modules to seed the dependencies.',
    ),
    'aliases' => array('obs'),
  );
  $items['drux-generate'] = array(
    'description' => 'Generate a list of dependencies[] = .. to copy+paste into a module info file.',
    'arguments' => array(
      'modules' => 'List of modules to seed the dependencies.',
    ),
    'aliases' => array('obs-gen'),
  );
  return $items;
}


function drush_drux_list_dependencies() {

  $extinfo = new drux_ExtensionInfo();
  $enabled = $extinfo->enabledKeys();
  $enabled = array_combine($enabled, $enabled);
  $toBeEnabled = array();
  foreach ($enabled as $module) {
    $dependencies = $extinfo->moduleDirectDependencies($module);
    if (!is_array($dependencies)) {
      continue;
    }
    foreach ($dependencies as $dependency_data) {
      if (!$extinfo->dependencySatisfied($dependency_data)) {
        $name = $dependency_data['name'];
        $toBeEnabled[$name][$module] = $module;
      }
    }
  }
  if (empty($toBeEnabled)) {
    drush_log(dt("All required extensions are already enabled."), 'ok');
  }
  else {
    $rows = $extinfo->modulesTableRows(array_keys($toBeEnabled));
    foreach ($toBeEnabled as $module => $requiredBy) {
      $requiredByText = implode(', ', $requiredBy);
      $rows[$module][] = $requiredByText;
    }
    array_unshift($rows, array('Extension', 'Human name', 'Type', 'Required by'));
    drush_print_table($rows, TRUE);
  }
}


function drush_drux_enable_dependencies() {

  // xautoload should find that..
  $extinfo = new drux_ExtensionInfo();
  $tracker = new drux_DependencyTracker($extinfo);
  $tracker->requireModules($extinfo->enabledKeys(), '(currently enabled)');

  while ($tracker->nModulesToDownload()) {
    $continue = $tracker->reportToBeEnabled();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $continue = $tracker->confirmDownload();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $tracker->refresh();
  }

  if ($tracker->nModulesToEnable()) {
    $continue = $tracker->reportToBeEnabled();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $continue = $tracker->confirmEnable();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $tracker->refresh();
  }
  else {
    drush_log(dt("All required extensions are already enabled."), 'ok');
  }
}


function drush_drux_find_obsolete() {

  $args = drux_parse_arguments(func_get_args());

  // xautoload should find that..
  $extinfo = new drux_ExtensionInfo();
  $tracker = new drux_DependencyTracker($extinfo);
  $tracker->requireModules($args, '(drush obs)');
  $obsolete = $tracker->obsoleteModules();
  if (empty($obsolete)) {
    drush_log(dt("All enabled modules are direct or indirect dependencies of the modules you specified."), 'ok');
  }
  else {
    $rows = $extinfo->modulesTableRows($obsolete);
    array_unshift($rows, array('Module', 'Human name'));
    drush_print_table($rows, TRUE);
  }
}


function drush_drux_generate() {

  $args = drux_parse_arguments(func_get_args());

  // xautoload should find that..
  $extinfo = new drux_ExtensionInfo();
  $tracker = new drux_DependencyTracker($extinfo);
  $tracker->requireModules($args, '(drush obs-gen)');
  $obsolete = $tracker->obsoleteModules();
  if (empty($obsolete)) {
    drush_log(dt("All enabled modules are direct or indirect dependencies of the modules you specified."), 'ok');
  }
  else {
    foreach ($obsolete as $module) {
      drush_print('dependencies[] = ' . $module);
    }
  }
}


/**
 * Sanitize user provided arguments to several pm commands.
 *
 * Return an array of arguments off a space and/or comma separated values. It also
 * lowercase arguments and optionally convert dashes to underscores.
 *
 * @param string[] $args
 * @param bool $dashes_to_underscores
 * @param bool $lowercase
 * @return string[]
 */
function drux_parse_arguments($args, $dashes_to_underscores = TRUE, $lowercase = TRUE) {
  // @todo Is this really needed? The $args already is an array.
  $arguments = _convert_csv_to_array($args);
  foreach ($arguments as $key => $argument) {
    $argument = ($dashes_to_underscores) ? strtr($argument, '-', '_') : $argument;
    if ($lowercase) {
      $arguments[$key] = strtolower($argument);
    }
  }
  return $arguments;
}



