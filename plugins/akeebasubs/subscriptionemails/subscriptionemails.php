<?php
/**
 * @package		akeebasubs
 * @copyright	Copyright (c)2010-2011 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die();

class plgAkeebasubsSubscriptionemails extends JPlugin
{
	/**
	 * Public constructor. Overridden to load the language strings.
	 */
	public function __construct(& $subject, $config = array())
	{
		if(!version_compare(JVERSION, '1.6.0', 'ge')) {
			if(!is_object($config['params'])) {
				$config['params'] = new JParameter($config['params']);
			}
		}
		parent::__construct($subject, $config);
		
		// Load the language files
		$jlang =& JFactory::getLanguage();
		$jlang->load('plg_akeebasubs_subscriptionemails', JPATH_ADMINISTRATOR, 'en-GB', true);
		$jlang->load('plg_akeebasubs_subscriptionemails', JPATH_ADMINISTRATOR, $jlang->getDefault(), true);
		$jlang->load('plg_akeebasubs_subscriptionemails', JPATH_ADMINISTRATOR, null, true);
		
		$jlang->load('com_akeebasubs', JPATH_ADMINISTRATOR, 'en-GB', true);
		$jlang->load('com_akeebasubs', JPATH_ADMINISTRATOR, $jlang->getDefault(), true);
		$jlang->load('com_akeebasubs', JPATH_ADMINISTRATOR, null, true);
	}

	/**
	 * Called when a new subscription is created, either manually or through
	 * the front-end interface
	 */
	public function onAKSubscriptionCreate($row)
	{
		// Only send out an email if the subscription is enabled. Otherwise, the
		// user may get confused as to why he received the email.
		if($row->enabled) $this->sendEmail($row, true);
	}
	
	/**
	 * Called whenever a subscription is modified. Namely, when its enabled status,
	 * payment status or valid from/to dates are changed.
	 */
	public function onAKSubscriptionChange($row)
	{
		// If the subscription is disabled and contact_flag is 3, do not send out
		// an expiration notification. The flag is set to 3 only when a user has
		// already renewed his subscription.
		if(!$row->enabled && ($row->contact_flag == 3)) return;
		
		// In all other cases, send out an email
		$this->sendEmail($row, false);
	}
	
	/**
	 * Sends out the email to the owner of the subscription.
	 * 
	 * @param $row The subscription row object
	 * @param $new bool True if it's a new subscription, false if it's an existing subscription being modified
	 */
	private function sendEmail($row, $new = true)
	{
		// Get the site name
		$config = JFactory::getConfig();
		$sitename = $config->getValue('config.sitename');
	
		// Get the user object
		$user = JFactory::getUser($row->user_id);
		
		// Get the level
		$level = FOFModel::getTmpInstance('Levels','AkeebasubsModel')
			->setId($row->akeebasubs_level_id)
			->getItem();
			
		// Get the from/to dates
		jimport('joomla.utilities.date');
		$jFrom = new JDate($row->publish_up);
		$jTo = new JDate($row->publish_down);
		
		// Get the "my subscriptions" URL
		$baseURL = JURI::base();
		$baseURL = str_replace('/administrator', '', $baseURL);
		$subpathURL = JURI::base(true);
		$subpathURL = str_replace('/administrator', '', $subpathURL);
		
		$url = str_replace('&amp;','&', JRoute::_('index.php?option=com_akeebasubs&view=subscriptions&layout=default'));
		if(substr($url,0,14) == '/administrator') $url = substr($url,14);
		$url = ltrim($url, '/');
		$subpathURL = ltrim($subpathURL, '/');
		if(substr($url,0,strlen($subpathURL)+1) == "$subpathURL/") $url = substr($url,strlen($subpathURL)+2);
		$url = $baseURL.$url;

		
		if($new) {
			$subject_key = 'PLG_AKEEBASUBS_SUBSCRIPTIONEMAILS_NEWHEADER';
			$body_key = 'PLG_AKEEBASUBS_SUBSCRIPTIONEMAILS_NEWBODY';
		} else {
			$subject_key = 'PLG_AKEEBASUBS_SUBSCRIPTIONEMAILS_MODHEADER';
			$body_key = 'PLG_AKEEBASUBS_SUBSCRIPTIONEMAILS_MODBODY';
		}
		
		$subject = JText::sprintf($subject_key, $sitename);
		$body = JText::sprintf($body_key,
			$user->name,
			$sitename,
			$user->username,
			$level->title,
			$row->enabled ? JText::_('Enabled') : JText::_('Disabled'),
			JText::_('COM_AKEEBASUBS_SUBSCRIPTION_STATE_'.$row->state),
			version_compare(JVERSION, '1.6', 'ge') ? $jFrom->format(JText::_('DATE_FORMAT_LC2')) : $jFrom->toFormat(JText::_('DATE_FORMAT_LC2')),
			version_compare(JVERSION, '1.6', 'ge') ? $jTo->format(JText::_('DATE_FORMAT_LC2')) : $jTo->toFormat(JText::_('DATE_FORMAT_LC2')),
			$url,
			$sitename
		);
		
		// DEBUG ---
		/* *
		echo "<p><strong>From</strong>: ".$config->getvalue('config.fromname')." &lt;".$config->getvalue('config.mailfrom')."&gt;<br/><strong>To: </strong>".$user->email."</p><hr/><p>$subject</p><hr/><p>".nl2br($body)."</p>"; die();
		/* */
		// -- DEBUG
		
		// Send the email
		$mailer = JFactory::getMailer();
		$mailer->setSender(array( $config->getvalue('config.mailfrom'), $config->getvalue('config.fromname') ));
		$mailer->addRecipient($user->email);
		$mailer->setSubject($subject);
		$mailer->setBody($body);
		$mailer->Send();	
	}
}