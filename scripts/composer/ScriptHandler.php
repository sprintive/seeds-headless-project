<?php
namespace Seeds\composer;

use Composer\Script\Event;
use Drupal\Core\Site\Settings;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * Seeds Composer Script Handler.
 */
class ScriptHandler
{
  /**
   * Get Drupal root directory.
   *
   * @param string $root
   *   Project root directory.
   *
   * @return string
   *   Drupal root path.
   */
  protected static function getDrupalRoot($root) {
    return $root . '/public_html';
  }

  /**
   * Remove .git folder from modules, themes, profiles of development branches.
   */
  public static function removeGitDirectories(Event $event) {
    $exclude = $event->getComposer()->getConfig()->get('seeds-exclude');
    $root = static::getDrupalRoot(getcwd());
    if (is_array($exclude)) {
      // Map exclude array, make all strings into '*/STRING/*';.
      $exclude = array_map(function ($item) {
        return '*/' . $item . '/*';
      }, $exclude);
      $suffix = '-not -path ' . implode(' -not -path ', $exclude);
      exec("find $root -type d -name .git $suffix -exec rm -rf {} +");
    } else {
      exec('find ' . $root . ' -name \'.git\' | xargs rm -rf');
    }
  }

    /**
   * Moves the 'translations.yml' file from the root to Drupal Root.
   */
  public static function moveTranslationsYmlFile() {
    $root = getcwd();
    if (file_exists("$root/translations.yml")) {
      exec("mv $root/translations.yml $root/public_html/translations.yml");
    }
  }

  public static function createRequiredFiles(Event $event) {
    $fs = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    $dirs = [
      'modules',
      'profiles',
      'themes',
    ];

    // Required for unit testing
    foreach ($dirs as $dir) {
      if (!$fs->exists($drupalRoot . '/' . $dir)) {
        $fs->mkdir($drupalRoot . '/' . $dir);
        $fs->touch($drupalRoot . '/' . $dir . '/.gitkeep');
      }
    }

    // Prepare the settings file for installation
    if (!$fs->exists($drupalRoot . '/sites/default/settings.php') && $fs->exists($drupalRoot . '/sites/default/default.settings.php')) {
      $fs->copy($drupalRoot . '/sites/default/default.settings.php', $drupalRoot . '/sites/default/settings.php');
      require_once $drupalRoot . '/core/includes/bootstrap.inc';
      require_once $drupalRoot . '/core/includes/install.inc';
      new Settings([]);
      $settings['settings']['config_sync_directory'] = (object) [
        'value' => Path::makeRelative('sites/default/config', $drupalRoot),
        'required' => TRUE,
      ];
      drupal_rewrite_settings($settings, $drupalRoot . '/sites/default/settings.php');
      $fs->chmod($drupalRoot . '/sites/default/settings.php', 0777);
      $event->getIO()->write("Created a sites/default/settings.php file with chmod 0777");
    }

    // Create the files directory with chmod 0777
    if (!$fs->exists($drupalRoot . '/sites/default/files')) {
      $oldmask = umask(0);
      $fs->mkdir($drupalRoot . '/sites/default/files', 0777);
      umask($oldmask);
      $event->getIO()->write("Created a sites/default/files directory with chmod 0777");
    }
    // Create the config directory with chmod 0777
    if (!$fs->exists($drupalRoot . '/sites/default/config')) {
      $oldmask = umask(0);
      $fs->mkdir($drupalRoot . '/sites/default/config', 0777);
      umask($oldmask);
      $event->getIO()->write("Created a sites/default/config directory with chmod 0777");
    }
    $pathKey = dirname($drupalRoot) . '/key';
    // Create the key directory with chmod 0777
    if (!$fs->exists($pathKey)) {
      $oldmask = umask(0);
      $fs->mkdir($pathKey, 0777);
      umask($oldmask);
      $event->getIO()->write("Created a key directory with chmod 0777");
    }
    // Create the config directory with chmod 0777
    if (!$fs->exists($drupalRoot . '/sites/default/local.settings.inc')) {
      $oldmask = umask(0);
      $fs->touch($drupalRoot . '/sites/default/local.settings.inc');
      umask($oldmask);
      $event->getIO()->write("Created a sites/default/local.settings.inc directory with chmod 0777");
    }
  }
}
