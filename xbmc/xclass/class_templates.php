<?php
/**
 * Copyright 2012 Team XBMC, All Rights Reserved
 *
 * Website: http://xbmc.org
 * Author: da-anda
 */

class xbmc_templates extends templates
{
	
	/**
	 * @var string
	 */
	protected $templateFile = "";

	/**
	 * ID of the XBMC theme to listen for
	 * @var int
	 */
	protected $themeId;

	/**
	 * ID of the custom XBMC template set that should be synced with the file system
	 * @var int
	 */
	protected $templateSetId;

	/**
	 * @var SimpleXML object
	 */
	protected static $template_xml;

	/**
	 * Flag that indicates if missing template files in file level should be created/extracted from the local themes XML
	 * @var boolean
	 */
	protected $createMissingTemplates = FALSE;

	protected $firstLevelCache = array();

	protected $updateCheck = FALSE;

	public $alwaysUpdateCache = FALSE;

	/**
	 * allows to set the template file to use from the outside
	 *
	 * @param string $pathToFile The absolute path to the template XML file
	 * @return void
	 */
	public function setTemplateFile($pathToFile) {
		$this->templateFile = $pathToFile;
	}

	/**
	 * allows to set the theme ID from the outside
	 *
	 * @param integer $themeId The ID of the theme
	 * @return void
	 */
	public function setTheme($themeId) {
		$this->themeId = $themeId;
	}

	/**
	 * allows to set the template set ID from the outside
	 *
	 * @param integer $themeId The ID of the template set
	 * @return void
	 */
	public function setTemplateSet($templateSetId) {
		$this->templateSetId = $templateSetId;
	}


	public function cache($templates) {
		parent::cache($templates);

		global $db, $theme, $mybb, $cache, $stylesheets, $stylesheet_scripts;

		// only perform our custom code once - just to be sure
		if (!$this->updateCheck) {
			$this->updateCheck = TRUE;

			if ($this->themeId == $theme['tid']) {
				$file = 'xbmc/css/styles.css';
				if (@is_file(MYBB_ROOT . '/' . $file)) {
					$customStyles = array();
					foreach ($theme['stylesheets'] as $scope => $sheets) {
						if ($scope != 'inherited') {
							foreach ($sheets as $themeName => $sheet) {
								if ($themeName != 'global') {
									$customStyles[$scope][$themeName] = $sheet;
								}
							}
						}
					}
					$customStyles['global']['global'][0] = $file . '?' . filemtime(MYBB_ROOT . $file);
					$theme['stylesheets'] = $customStyles;

					// unfortunately the $stylesheet variable is already precompiled before our code is executed
					// so until we find a better place for it, overwrite the variable
					$stylesheet_actions = array("global");
					if(isset($mybb->input['action']) && strlen($mybb->input['action'])) {
						$stylesheet_actions[] = $mybb->input['action'];
					}
					$newStylesheets = array();
					foreach($stylesheet_scripts as $stylesheet_script) {
						foreach($stylesheet_actions as $stylesheet_action) {
							if(isset($theme['stylesheets'][$stylesheet_script][$stylesheet_action])) {
								// Actually add the stylesheets to the list
								foreach($theme['stylesheets'][$stylesheet_script][$stylesheet_action] as $page_stylesheet) {
									if (!isset($newStylesheets[$page_stylesheet])) {
										$newStylesheets[$page_stylesheet] = "<link type=\"text/css\" rel=\"stylesheet\" href=\"{$mybb->settings['bburl']}/{$page_stylesheet}\" />\n";
									}
								}
							}
						}
					}
					$stylesheets = implode('', $newStylesheets);

				}
			}
	
			# check if the DB needs a update due to changed template files on file level
			# we do this, because templates on file level are easier to edit, especially using a IDE.
			# So if there are changed files, update the DB
			if ($this->templateSetId && $this->templateSetId == $theme['templateset']) {

					$templateDir = MYBB_ROOT . 'xbmc/templates/';
					$templateSettings = (array) unserialize($cache->read('xbmc_template'));

					if (@is_dir($templateDir) && ($this->alwaysUpdateCache || (filemtime($templateDir) > $templateSettings['lastUpdated']))) {
						$query = $db->simple_select('templates', '*', 'sid=' . $this->templateSetId);
						$templateFromDatabase = array();
						while($row = $db->fetch_array($query)) {
							$templateFromDatabase[$row['title']] = $row;
						}
						$db->free_result($query);
			
						$handle = opendir($templateDir);
						while (($file = readdir($handle)) !== FALSE) {
							if (!is_file($templateDir . $file)) { continue; }
			
							$fileInfo = pathinfo($file);
							$fileMtime = filemtime($templateDir . $file);
							if ($fileInfo['extension'] == 'html' && $fileMtime > $templateCache['lastUpdated']) {
								$template = file_get_contents($templateDir . $file);
			
								$templateData = array(
									'title' => $fileInfo['filename'],
									'template' => $db->escape_string($template),
									'dateline' => $fileMtime,
									'sid' => $this->templateSetId
								);

								if (isset($templateFromDatabase[$fileInfo['filename']])) {
									$db->update_query('templates', $templateData, 'tid=' . $templateFromDatabase[$fileInfo['filename']]['tid']);
								} else {
									$db->insert_query('templates', $templateData);
								}
			
								$this->cache[$fileInfo['filename']] = $template;
							}
						}
						
						$templateSettings['lastUpdated'] = time();
						$cache->update('xbmc_template', serialize($templateSettings));
					}
			
			/*
					# cleans up all unchanged templates in file level
					# use this bevore you export your templates
					
					$allTemplates = $db->simple_select("templates", "title,template", "sid IN ('-2','-1','".$theme['templateset']."')");
			
					while ($template = $db->fetch_array($allTemplates)) {
						$templateFile = '/var/www/mybb/xbmc/templates/' . $template['title'] . '.html';
						if (@file_exists($templateFile)) {
							$data = file_get_contents($templateFile);
			
							if (trim($data) == trim($template['template'])) {
								print_r($templateFile);
								unlink($templateFile);
							}
						}
					}
			*/
			}
		}
	}

	/**
	 * Fetch a template directly from the install/resources/mybb_theme.xml directory if it exists (DEVELOPMENT MODE)
	 */
	public function dev_get($title)
	{
		global $stylesheets, $mybb, $cache, $db, $theme;

		if ($this->themeId != $theme['tid']) {
			return FALSE;
		}

		if ($title == 'headerinclude') {
			$file = '/xbmc/css/styles.css';
			if (@is_file(MYBB_ROOT . '/' . $file)) {
				$stylesheets = '<link type="text/css" rel="stylesheet" href="' . $mybb->settings['bburl'] . $file . '?' . filemtime(MYBB_ROOT . $file) . '" />';
			}
		}

		# use this code if you like to work on file level only or to extract
		# missing template files from the current theme to the file level for easier edit

		$templateFile = '/var/www/mybb/xbmc/templates/' . $title . '.html';

		if (!isset($this->firstLevelCache[$templateFile])) {
			if (@file_exists($templateFile)) {
				$this->firstLevelCache[$templateFile] = file_get_contents($templateFile);
			} else if ($this->createMissingTemplates) {
				if(!$this->template_xml)
				{
					if(@file_exists($this->templateFile))
					{
						$this->template_xml = simplexml_load_file($this->templateFile);
					}
					else
					{
						return false;
					}
				}

				$res = $this->template_xml->xpath("//template[@name='{$title}']");
				$data = $res[0];
				$this->firstLevelCache[$templateFile] = $data;

				if ($data) {
					$templateHandle = fopen($templateFile, 'w');
					fwrite($templateHandle, $data);
					fclose($templateHandle);
				}
			}
		}
		return $this->firstLevelCache[$templateFile];
	}
}
?>