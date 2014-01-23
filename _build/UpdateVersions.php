<?php
require_once dirname(__FILE__) . '/../www/config.php';

if (PHP_SAPI == 'cli') {
	$vers = new UpdateVersions();
	$vers->checkAll();
//	$vers->save();
}

class UpdateVersions
{
	/**
	 * @var object ColorCLI
	 */
	public $out;

	/**
	 * Indicates whether any element of the XML has been changed/updated.
	 * Any methods that alter the XML in any way should set this flag
	 * @var boolean
	 */
	protected $_changed = false;

	/**
	 * @var string	Path and filename for the XML file.
	 */
	protected $_filespec;

	/**
	 * @var object Sites/Settings
	 */
	protected $_settings;

	/**
	 * @var object simpleXMLElement
	 */
	protected $_vers;

	/**
	 * Class constructor initialises the SimpleXML object and sets a few properties.
	 * @param string $filepath Optional filespec for the XML file to use. Will use default otherwise.
	 * @throws Exception If the XML is invalid.
	 */
	public function __construct($filepath = null)
	{
		if (empty($filepath)) {
			$filepath = nZEDb_VERSIONS;
		}
		$this->_filespec = $filepath;

		$this->out = new ColorCLI();
		$this->_versions = @new SimpleXMLElement($filepath, 0, true);
		if ($this->_versions === false) {
			$this->out->error("Your versioning XML file ({nZEDb_VERSIONS}) is broken, try updating from git.\n");
			throw new Exception("Failed to open versions XML file '$filename'");
		}

		$s = new Sites();
		$this->_settings = $s->get();
	}

	/**
	 * Run all checks
	 * @param boolean $update Whether the XML should be updated by the check.
	 * @return boolean	True if any of the checks actually caused an update (not if it indicated one was needed), flase otherwise
	 */
	public function checkCheck($update = true)
	{
		$this->checkDb($update);
		$this->checkGitCommit($update);
		$this->checkGitTag($update);
		return $this->isUpdated();
	}

	/**
	 * Checks the database sqlpatch setting against the XML's stored value.
	 * @param boolean $update Whether the XML should be updated by the check.
	 * @return boolean True if the database sqlpatch version is more than that of XML element (.i.e. needs updating).
	 */
	public function checkDb($update = true)
	{
		if ($this->vers->nzedb->db < $this->_settings->sqlpatch) {
			if ($update) {
				echo $this->out->primary("Updating Db revision\n");
				$this->vers->nzedb->db = $this->_settings->sqlpatch;
				$this->_changed = true;
			}
			return true;
		}
		return false;
	}

	/**
	 * Checks the git commit number against the XML's stored value.
	 * @param boolean $update Whether the XML should be updated by the check.
	 * @return boolean True if the dgit commit number is more than that of XML element (.i.e. needs updating).
	 */
	public function checkGitCommit($update = true)
	{
		exec('git log | grep "^commit" | wc -l', $output);
		if ($this->vers->nzedb->commit < $output[0]) {
			if ($update) {
				echo $this->out->primary("pdating commit number\n");
				$this->vers->nzedb->commit = $output[0];
				$this->_changed = true;
			}
			return true;
		}
		return false;
	}

	/**
	 * Checks the git's latest version tag against the XML's stored value. Version should be
	 * Major.Minor.Revision (Note commit number is NOT revision)
	 * @param boolean $update Whether the XML should be updated by the check.
	 * @return boolean True if the git's latest version tag is higher than that of XML element (.i.e. needs updating).
	 */
	public function checkGitTag($update = true)
	{
		exec('git log --tags', $output);
		$index = 0;
		$count = count($output);
		while (!preg_match('#v(\d+\.\d+\.\d+)#i', $output[$index], $match) && $count < $index ) {
			$index++;
		}

		// TODO this needs a better test. Think PHP has a way to do this, will update later.
		if (!empty($match) && $this->vers->nzedb->tag < $match) {
			if ($update) {
				echo $this->out->primary("Updating tagged version\n");
				$this->vers->nzedb->tag = $match;
				$this->_changed = true;
			}
			return true;
		}
		return false;
	}
/*
	public function check($update = true)
	{
		if ($this->vers->setting) {
			if ($update) {
				echo $this->out->primary("\n");
				;
				$this->_changed = true;
			}
			return true;
		}
		return false;
	}
 */

	/**
	 * Check whether the XML has been changed by one of the methods here.
	 * @return boolean True if the XML has been changed.
	 */
	public function isUpdated()
	{
		return $this->_changed === true;
	}

	public function save()
	{
		if ($this->isUpdateed()) {
			$this->vers->asXML($this->_filespec);
		}
	}
}
?>