<?php


/**
 * This code is just adapted from drush pm, and sliced into separate methods.
 * It does not pretend to be quality code, but at least it's not one big
 * function.
 */
class drux_ExtensionInfo {

  protected $extensionInfo;

  function __construct() {
    $this->refresh();
  }

  function refresh() {
    $this->extensionInfo = drush_get_extensions();
  }

  function enabledKeys() {
    $result = array();
    foreach ($this->extensionInfo as $key => $info) {
      if ($info->status) {
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

  function drupalRequiredModules() {
    $result = array();
    foreach ($this->extensionInfo as $module => $info) {
      if (!empty($info->info['required'])) {
        $result[$module] = $module;
      }
    }
    return $result;
  }

  function moduleDependencies($module) {
    if (!isset($this->extensionInfo[$module])) {
      return NULL;
    }
    elseif (!isset($this->extensionInfo[$module]->requires)) {
      return array();
    }
    else {
      return array_keys($this->extensionInfo[$module]->requires);
    }
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
    $result = drush_invoke_process_args('pm-download', array_keys($projects), array('y' => TRUE));
    $this->refresh();
    return TRUE;
  }

  function enableExtensions($modules) {
    drush_module_enable($modules);
    drush_system_modules_form_submit(pm_module_list());
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

