<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

class MagicLinkWebclientModule extends AApiModule
{
	protected $oMinModuleDecorator;
	
	protected $aRequireModules = array(
		'Min'
	);
	
	protected $aSettingsMap = array(
		'RegisterModuleName' => array('StandardRegisterFormWebclient', 'string'),
		'LoginModuleName' => array('StandardLoginFormWebclient', 'string'),
		'EnableSendMagicLinkViaMail' => array(true, 'bool'),
	);
	
	/***** private functions *****/
	/**
	 * Initializes module.
	 * 
	 * @ignore
	 */
	public function init()
	{
		$this->subscribeEvent('Register::before', array($this, 'onBeforeRegister'));
		$this->subscribeEvent('Register::after', array($this, 'onAfterRegister'));
		
		$this->subscribeEvent('Core::CreateUser::after', array($this, 'onAfterCreateUser'));

		$this->subscribeEvent('StandardAuth::CreateUserAccount::after', array($this, 'onAfterCreateUserAccount'));
		
		$this->subscribeEvent('CreateOAuthAccount', array($this, 'onCreateOAuthAccount'));
		$this->subscribeEvent('Core::AfterDeleteUser', array($this, 'onAfterDeleteUser'));
		
		$this->includeTemplate('AdminPanelWebclient_EditUserView', 'Edit-User-After', 'templates/MagicLinkView.html', $this->sName);
		$this->includeTemplate('StandardAuthWebclient_AccountsSettingsView', 'Edit-Standard-Account-After', 'templates/AccountPasswordHintView.html', $this->sName);
	}
	
	/**
	* Returns Min module decorator.
	* 
	* @return \CApiModuleDecorator
	*/
	private function getMinModuleDecorator()
	{
		if ($this->oMinModuleDecorator === null)
		{
			$this->oMinModuleDecorator = \CApi::GetModuleDecorator('Min');
		}
		
		return $this->oMinModuleDecorator;
	}
	
	/**
	 * Returns register module hash.
	 * 
	 * @return string
	 */
	protected function getRegisterModuleHash()
	{
		$oRegisterModuleDecorator = \CApi::GetModuleDecorator($this->getConfig('RegisterModuleName'));
		$oRegisterModuleSettings = $oRegisterModuleDecorator->GetAppData();
		return $oRegisterModuleSettings['HashModuleName'];
	}
	
	/**
	 * Returns login module hash.
	 * 
	 * @return string
	 */
	protected function getLoginModuleHash()
	{
		$oLoginModuleDecorator = \CApi::GetModuleDecorator($this->getConfig('LoginModuleName'));
		$oLoginModuleSettings = $oLoginModuleDecorator->GetAppData();
		return $oLoginModuleSettings['HashModuleName'];
	}
	
	/**
	 * Returns id for Min Module
	 * 
	 * @return string
	 */
	protected function generateMinId($iUserId)
	{
		return implode('|', array($this->GetName(), $iUserId, md5($iUserId)));
	}

	/**
	 * Returns user with identificator obtained from the magic link hash.
	 * 
	 * @param string $sMagicLinkHash Magic link hash.
	 * @return \CUser
	 */
	protected function getUserByMagicLinkHash($sMagicLinkHash)
	{
		$oUser = null;
		$oMin = $this->getMinModuleDecorator();
		if ($oMin)
		{
			$mHash = $oMin->GetMinByHash($sMagicLinkHash);
			if (isset($mHash['__hash__'], $mHash['UserId']) && !isset($mHash['Registered']))
			{
				$iUserId = $mHash['UserId'];
				$oCore = \CApi::GetModuleDecorator('Core');
				if ($oCore)
				{
					$oUser = $oCore->GetUser($iUserId);
				}
			}
		}
		return $oUser;
	}
	
	/**
	 * Writes to $aParams['UserId'] user identificator obtained from magic link hash.
	 * 
	 * @ignore
	 * @param array $aParams Is passed by reference.
	 */
	public function onBeforeRegister(&$aParams)
	{
		$sMagicLinkHash = $aParams['MagicLinkHash'];
		if (!empty($sMagicLinkHash))
		{
			$oUser = $this->getUserByMagicLinkHash($sMagicLinkHash);
			if ($oUser)
			{
				$aParams['UserId'] = $oUser->iId;
			}
		}
	}
	
	/**
	 * Updates magic link hash in Min module.
	 * 
	 * @ignore
	 * @param array $aParams Is passed by reference.
	 */
	public function onAfterRegister(&$aParams)
	{
		$sMagicLinkHash = $aParams['MagicLinkHash'];
		if (!empty($sMagicLinkHash))
		{
			$oMin = $this->getMinModuleDecorator();
			if ($oMin)
			{
				$mHash = $oMin->GetMinByHash($sMagicLinkHash);
				if (isset($mHash['__hash__'], $mHash['UserId']) && !isset($mHash['Registered']))
				{
					$mHash['Registered'] = true;
					$oMin->UpdateMinByHash($sMagicLinkHash, $mHash);
				}
			}
		}
	}
	
	/**
	 * Updates magic link hash in Min module for user with $aData['UserId'] identificator.
	 * 
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onAfterCreateUserAccount($aData)
	{
		$oMin = $this->getMinModuleDecorator();
		if (isset($aData['UserId']) && $oMin)
		{
			$iUserId = $aData['UserId'];
			$mHash = $oMin->GetMinById(
				$this->generateMinId($iUserId)
			);
			
			if (isset($mHash['__hash__'], $mHash['UserId']) && !isset($mHash['Registered']))
			{
				$mHash['Registered'] = true;
				$oMin->UpdateMinByHash($mHash['__hash__'], $mHash);
			}
		}
	}
	
	/**
	 * Writes to $oUser variable user object for magic link hash from cookie.
	 * 
	 * @ignore
	 * @param \CUser $oUser
	 */
	public function onCreateOAuthAccount(&$oUser)
	{
		if (isset($_COOKIE['MagicLinkHash']))
		{
			$sMagicLinkHash = $_COOKIE['MagicLinkHash'];
			
			$oFoundUser = $this->getUserByMagicLinkHash($sMagicLinkHash);
			if (!empty($oFoundUser))
			{
				unset($_COOKIE['MagicLinkHash']);
				$oUser = $oFoundUser;
				
				$oMin = $this->getMinModuleDecorator();
				if ($oMin)
				{
					$mHash = $oMin->GetMinByHash($sMagicLinkHash);
					if (isset($mHash['__hash__'], $mHash['UserId']) && !isset($mHash['Registered']))
					{
						$mHash['Registered'] = true;
						$oMin->UpdateMinByHash($sMagicLinkHash, $mHash);
					}
				}
			}
		}
	}
	
	/**
	 * Updates magic link hash in Min module for user with $aData['UserId'] identificator.
	 * 
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onAfterCreateUser($aData)
	{
		$iUserId = isset($aData['@Result']) && (int) $aData['@Result'] > 0 ? $aData['@Result'] : 0;
		if (0 < $iUserId)
		{
			$this->CreateMagikLinkHash($iUserId);
		}
	}	
	
	/**
	 * Deletes hash which are owened by the specified user.
	 * 
	 * @ignore
	 * @param int $iUserId User Identificator.
	 */
	public function onAfterDeleteUser($iUserId)
	{
		$oMin = $this->getMinModuleDecorator();
		if ($oMin)
		{
			$oMin->DeleteMinByID(
				$this->generateMinId($iUserId)
			);
		}
	}
	/***** private functions *****/
	
	/***** public functions might be called with web API *****/
	/**
	 * Obtaines list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetAppData()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return array(
			'RegisterModuleHash' => $this->getRegisterModuleHash(),
			'RegisterModuleName' => $this->getConfig('RegisterModuleName'),
			'LoginModuleHash' => $this->getLoginModuleHash(),
			'EnableSendMagicLinkViaMail' => $this->getConfig('EnableSendMagicLinkViaMail'),
		);
	}
	
	/**
	 * Create magic link hash for specified user.
	 * 
	 * @param int $UserId User identificator.
	 * @return string
	 */
	public function CreateMagikLinkHash($UserId)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mHash = '';
		$oMin = $this->getMinModuleDecorator();
		if ($oMin)
		{
			$sMinId = $this->generateMinId($UserId);
			$aHashData = $oMin->GetMinById($sMinId);
			if (!$aHashData)
			{
				$mHash = $oMin->CreateMin(
					$sMinId,
					array(
						'UserId' => $UserId
					)
				);
			}
			else
			{
				$mHash = $this->GetMagicLinkHash($UserId);
			}
		}
		
		return $mHash;
	}
	
	/**
	 * Returns magic link hash for specified user.
	 * 
	 * @param int $UserId User identificator.
	 * @return string
	 */
	public function GetMagicLinkHash($UserId)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mHash = '';
		$oMin = $this->getMinModuleDecorator();
		if ($oMin)
		{
			$sMinId = $this->generateMinId($UserId);
			$mHash = $oMin->GetMinById($sMinId);
			
			if ($mHash)
			{
				if (isset($mHash['__hash__']) && !isset($mHash['Registered']))
				{
					$mHash = $mHash['__hash__'];
				}
				else
				{
					$mHash = '';
				}
			}
		}
		
		$oAuthenticatedUser = \CApi::getAuthenticatedUser();
		if (empty($oAuthenticatedUser) || $oAuthenticatedUser->Role !== \EUserRole::SuperAdmin)
		{
			return '';
		}
		
		return $mHash;
	}
	
	/**
	 * Returns public id of user obtained from magic link hash.
	 * 
	 * @param string $MagicLinkHash Magic link hash with information about user and its registration status.
	 * @return string
	 */
	public function GetUserPublicId($MagicLinkHash)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oUser = $this->getUserByMagicLinkHash($MagicLinkHash);
		if ($oUser)
		{
			return $oUser->PublicId;
		}
		return '';
	}
	/***** public functions might be called with web API *****/
}
