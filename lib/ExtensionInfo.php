<?php


/**
 * This code is just adapted from drush pm, and sliced into separate methods.
 * It does not pretend to be quality code, but at least it's not one big
 * function.
 */
class drux_ExtensionInfo {

  /**
   * @var stdClass[]
   */
  protected $extensionInfo;

  function __construct() {
    $this->refresh();
  }

  function refresh() {
    $this->extensionInfo = drush_get_extensions();
  }

  /**
   * @param bool $include_profiles
   *   If TRUE, install profiles will be included in the listing.
   *   If FALSE, install profiles will be excluded.
   *
   * @return string[]
   *   Enabled modules.
   */
  function enabledKeys($include_profiles = FALSE) {
    $result = array();
    foreach ($this->extensionInfo as $key => $info) {
      if (1
        && !empty($info->status)
        && ($include_profiles || '.profile' !== substr($info->filename, -8))
      ) {
        $result[] = $key;
      }
    }
    return $result;
  }

  function modulesTableRows($modules) {
    $rows = array();
    foreach ($modules as $module) {
      $info = $this->extensionInfo[$module];
      if (isset($info)) {
        $rows[$module] = array(
          $module, $info->info['name'], $info->type,
        );
      }
      else {
        $rows[$module] = array(
          $module, '', '',
        );
      }
    }
    return $rows;
  }

  /**
   * Modules where the module info says they are required.
   * This is usually only the required core modules, and the active install
   * profile.
   *
   * @param bool $include_profiles
   *   If TRUE, install profiles will be included in the listing.
   *   If FALSE, install profiles will be excluded.
   *
   * @return string[]
   *   Required modules, such as 'system'.
   */
  function drupalRequiredModules($include_profiles = FALSE) {
    $result = array();
    foreach ($this->extensionInfo as $module => $info) {
      if (1
        && !empty($info->info['required'])
        && ($include_profiles || '.profile' !== substr($info->filename, -8))
      ) {
        $result[$module] = $module;
      }
    }
    return $result;
  }

  /**
   * Get the dependencies of a given module.
   *
   * @param string $module
   *   The module name to start from.
   * @return string[]|NULL
   *   An array of module names, or NULL if we don't have that information.
   */
  function moduleDependencies($module) {
    if (!isset($this->extensionInfo[$module])) {
      return NULL;
    }
    elseif (!isset($this->extensionInfo[$module]->requires)) {
      return array();
    }
    else {
      // return $this->extensionInfo[$module]->info['dependencies'];
      return array_keys($this->extensionInfo[$module]->requires);
    }
  }

  /**
   * Get the dependencies of a given module.
   *
   * @param string $module
   *   The module name to start from.
   * @return string[]|NULL
   *   An array of module names, or NULL if we don't have that information.
   */
  function moduleDirectDependencies($module) {
    if (!isset($this->extensionInfo[$module])) {
      return NULL;
    }
    $info = $this->extensionInfo[$module];
    if (!isset($info->requires)) {
      return array();
    }
    if (!is_array($info->info['dependencies'])) {
      return array();
    }
    $deps = array();
    foreach ($info->info['dependencies'] as $dependency) {
      $dependency_data = drupal_parse_dependency($dependency);
      $deps[] = $dependency_data;
    }
    return $deps;
  }

  /**
   * Check if the module required in $dependency_data is enabled and has the
   * correct version.
   *
   * @param array $dependency_data
   *
   * @return bool
   */
  function dependencySatisfied($dependency_data) {
    $name = $dependency_data['name'];
    if (!isset($this->extensionInfo[$name])) {
      return FALSE;
    }
    $info = $this->extensionInfo[$name];
    if (empty($info->status)) {
      return FALSE;
    }
    return TRUE;
  }

  function moduleRequiredBy($module) {
    if (!isset($this->extensionInfo[$module])) {
      return NULL;
    }
    elseif (!isset($this->extensionInfo[$module]->required_by)) {
      return array();
    }
    else {
      return array_keys($this->extensionInfo[$module]->required_by);
    }
  }

  function moduleStatus($module) {
    if (isset($this->extensionInfo[$module])) {
      return $this->extensionInfo[$module]->status;
    }
  }

  function classify(&$list) {
    $modules = array();
    $themes = array();
    drush_pm_classify_extensions($list, $modules, $themes, $this->extensionInfo);
    return array($modules, $themes);
  }

  function checkCompatibility($name) {
    if (!isset($this->extensionInfo[$name])) {
      return NULL;
    }
    $info = $this->extensionInfo[$name];
    return (
      isset($info->info['core']) &&
      $info->info['core'] == DRUPAL_CORE_COMPATIBILITY &&
      version_compare(phpversion(), $info->info['php']) >= 0
    );
  }

  function downloadProjects($projects) {
    $result = drush_invoke_process('@self','pm-download', $projects, array('y' => TRUE));
    $this->refresh();
    return TRUE;
  }

  function enableExtensions($modules) {
    drush_module_enable($modules);
    // drush_system_modules_form_submit(pm_module_list());
    $this->refresh();
    $result = array(array(), array());
    foreach ($modules as $module) {
      $result[$this->moduleStatus($module) ? 0 : 1][$module] = $module;
    }
    return $result;
  }

  function extensionPath($name) {
    return dirname($this->extensionInfo[$name]->filename);
  }
}

