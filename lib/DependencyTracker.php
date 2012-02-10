<?php


class drux_DependencyTracker {

  protected $extinfo;
  protected $en = array();
  protected $dl = array();
  protected $visited = array();
  protected $obsolete = array();

  function __construct($extensionInfo) {
    $this->extinfo = $extensionInfo;
    $enabled = $extensionInfo->enabledKeys();
    $this->obsolete = array_combine($enabled, $enabled);
    $this->requireModules($extensionInfo->drupalRequiredModules());
  }

  function requireModules($modules, $required_by = '(drupal core)') {
    foreach ($modules as $module) {
      if (!isset($this->visited[$module])) {
        $this->visited[$module] = $module;
        $this->_requireModule($module, $required_by);
        $dependencies = $this->extinfo->moduleDependencies($module);
        if (!empty($dependencies)) {
          $this->requireModules($dependencies, $module);
        }
      }
    }
  }

  function nModulesToDownload() {
    return count($this->dl);
  }

  function nModulesToEnable() {
    return count($this->en);
  }

  function obsoleteModules() {
    return $this->obsolete;
  }

  function confirmDownload() {

    $projects = array();
    $not_found = array();
    foreach ($this->dl as $module) {
      $project = drush_pm_find_project_from_extension($module);
      if (empty($project)) {
        return FALSE;
      }
      $projects[$project][$module] = $module;
    }

    if (empty($projects)) {
      return TRUE;
    }

    $rows = array(array('Project name', 'Contains modules'));
    foreach ($projects as $project => $project_modules) {
      $rows[$project] = array(
        $project,
        implode(', ', $project_modules),
      );
    }
    drush_log("\n" . dt(
      "The following projects need to be downloaded."
    ), 'notice');
    drush_print_table($rows, TRUE);

    if (!drush_confirm(dt(
      "Would you like to continue downloading these projects?\n  !projects",
      array('!projects' => implode(', ', array_keys($projects)))
    ))) {
      return FALSE;
    }

    $success = $this->extinfo->downloadProjects(array_keys($projects));

    $this->refresh();

    return $success;
  }

  function reportToBeEnabled() {

    $rows = array();
    $incompatible = array();
    foreach ($this->en as $module => $required_by) {
      $ok = $this->extinfo->checkCompatibility($module);
      $note = '';
      if (!isset($ok)) {
        $note = dt('Not downloaded yet.');
      }
      elseif (!$ok) {
        $incompatible[$module] = $module;
        $note = dt('Not compatible');
      }
      $rows[$module] = array(
        $module,
        $note,
        dt("required by: !modules", array('!modules' => implode(', ', $required_by))),
      );
    }

    drush_log("\n" . dt(
      "The following extensions need to be enabled:"
    ), 'status');
    drush_print_table($rows);

    if (!empty($incompatible)) {
      drush_log(dt(
        "The following extensions are incompatible with the Drupal version:\n  !modules",
        array('!modules' => implode(', ', $incompatible))
      ), 'error');
      return FALSE;
    }

    return TRUE;
  }

  function confirmEnable() {

    if (!drush_confirm(dt(
      "Do you want to continue enabling these extensions?",
      array('!extensions' => implode(', ', array_keys($this->en)))
    ))) {
      return FALSE;
    }

    list($success, $fail) = $this->extinfo->enableExtensions(array_keys($this->en));

    $this->refresh();

    if (!empty($success)) {
      drush_log(dt(
        "The following extensions were enabled successfully:\n  !extensions",
        array('!extensions' => implode(', ', $success))
      ), 'success');
    }

    if (!empty($fail)) {
      drush_log(dt(
        "The following extensions failed to enable:\n  !extensions",
        array('!extensions' => implode(', ', $fail))
      ), 'error');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * This is called when new modules have been downloaded or enabled.
   */
  function refresh() {
    $missing = array_keys($this->dl + $this->en);
    $this->dl = array();
    $this->en = array();
    $this->visited = array();
    $this->requireModules($missing);
  }

  protected function _requireModule($module, $required_by) {
    $status = $this->extinfo->moduleStatus($module);
    if (!isset($status)) {
      $this->dl[$module] = $module;
    }
    if (!$status) {
      $this->en[$module][$required_by] = $required_by;
    }
    unset($this->obsolete[$module]);
  }
}
