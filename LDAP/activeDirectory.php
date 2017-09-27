<?php
/**
 * API for working with Active Directory
 *
 * Each method you could use as separate.
 * For denied not connected requests, is uses singleton, where constructor will make connection.
 * 	activeDirectory::authenticate($username, $password);
 * 	activeDirectory::getOffices();
 * 	activeDirectory::getDepartments();
 * 	activeDirectory::getSecurityGroups();
 * 	activeDirectory::getUsersInOffice($officeName);
 *	activeDirectory::getUserGroups($username);
 * 	activeDirectory::createUser($userData);
 * 	activeDirectory::updateUser($username, $userData);
 * 	activeDirectory::deleteUserFromGroup($username, $groupName);
 * 	activeDirectory::addUserToGroup($username, $groupName);
 */
class activeDirectory {
	const PATH_INTERNAL = 'OU=_CompanyNameInAD,DC=internal,DC=CompanyNameInAD,DC=com';
	const PATH_GROUPS = 'OU=Main Security Groups,OU=Groups,OU=Share objects,OU=_CompanyNameInAD,DC=internal,DC=CompanyNameInAD,DC=com';
	const COMPANY_NAME = 'ShortNameCompany';

	/**
	 * Singleton
	 * @param activeDirectory
	 */
	private static $instance;

	/**
	 * Connection settings
	 * Notice. Should be your data
	 * @param array
	 */
	private static $settings = [
		'hostname' => '', //IP activeDirectory server
		'port' => '', //Port
		'domain' => '', //activeDirectory`s domain name
		'username' => '', //Root username
		'password' => '', //Root password
	];

	/**
	 * LDAP connection
	 * @param resource
	 */
	private $connection;

	/**
	 * Make from ldap values values for better using
	 * @type array
	 */
	private static $renamingLdapAttributes = [
		'samaccountname' => 'username',
		'givenname' => 'first_name',
		'sn' => 'last_name',
		'co' => 'office_name',
		'l' => 'office_name',
		'department' => 'department_name',
		'mail' => 'email',
		'mobile' => 'phone_number',
		'extensionattribute3' => 'date_birth',
		'whenchanged' => 'last_updated',
		'whencreated' => 'date_added',
		'title' => 'title',
		'useraccountcontrol' => 'is_active',
	];

	/**
	 * Check for authentication opportunity in Active Directory for individual user
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return bool
	 */
	public static function authenticate($username, $password) {
		if (empty($username) || !is_string($username)) {
			throw new InvalidArgumentException('Invalid username parameter');
		}

		if (empty($password) || !is_string($password)) {
			throw new InvalidArgumentException('Invalid password parameter');
		}

		$settings = self::$settings;
		$settings['username'] = $username;
		$settings['password'] = $password;

		$auth = new self($settings);
		$result = $auth->createConnection();

		return ($result === true);
	}

	/**
	 * Returns a list of all offices in the company
	 * @return array
	 * @throws RuntimeException
	 */
	public static function getOffices() {
		$adObj = self::instance();
		$result = $adObj->select(self::PATH_INTERNAL, '(&(objectCategory=OrganizationalUnit))');

		if (!isset($result['OU']) || !is_array($result['OU'])) {
			throw new RuntimeException('Invalid response');
		}

		$result = array_keys($result['OU']);
		$offices = [];

		foreach ($result  as $officeName) {
			if (empty($officeName) || $officeName == 'Share objects') {
				continue;
			}

			$offices[] = $officeName;
		}

		return $offices;
	}

	/**
	 * Get list of departments in the company
	 * @return array
	 * @throws RuntimeException
	 */
	public static function getDepartments() {
		$adObj = self::instance();
		$result = $adObj->select(self::PATH_INTERNAL, '(&(objectCategory=OrganizationalUnit))');

		if (!isset($result['OU']['Share objects']['OU']['Groups']['OU']['Main Security Groups']['OU'])) {
			throw new RuntimeException('The path "Share objects/Groups/Main Security Groups" is not found.');
		}

		$result = $result['OU']['Share objects']['OU']['Groups']['OU']['Main Security Groups']['OU'];

		if (!is_array($result)) {
			throw new RuntimeException('The path "Share objects/Groups/Main Security Groups" is not an array');
		}

		return array_keys($result);
	}

	/**
	 * Get list of security groups
	 * @return array
	 * @throws RuntimeException
	 */
	public static function getSecurityGroups() {
		$adObj = self::instance();
		$result = $adObj->select(self::PATH_GROUPS, '(&(objectCategory=Group))', array('name', 'distinguishedname', 'member' ), 'name');

		if (!isset($result['OU']['Share objects']['OU']['Groups']['OU']['Main Security Groups'])) {
			throw new RuntimeException('The path "Share objects/Groups/Main Security Groups" is not found.');
		}

		$result = $result['OU']['Share objects']['OU']['Groups']['OU']['Main Security Groups'];

		if (!is_array($result)) {
			throw new RuntimeException('The path "Share objects/Groups/Main Security Groups" is not an array');
		}

		return self::getGroupsFromRecord($result);
	}

	/**
	 * Return list of users in one office, or all users in all offices
	 * @param string $officeName
	 * @return array
	 * @throws RuntimeException
	 */
	public static function getUsersInOffice($officeName = null) {
		$adObj = self::instance();
		$attributes = array_keys(self::$renamingLdapAttributes);

		$result = $adObj->select(self::PATH_INTERNAL, '(&(objectCategory=User))', $attributes);

		if (!isset($result['OU']) || !is_array($result['OU'])) {
			throw new RuntimeException('Invalid response');
		}

		$users = [];

		foreach ($result['OU'] as $name => $officeData) {
			if ($name == 'Share objects') {
				continue;
			}

			$users[$name] = self::getUsersFromRecord($officeData);

			if ($name == $officeName) {
				return $users[$name];
			}
		}

		if (!is_null($officeName)) {
			return [];
		}

		return $users;
	}

	/**
	 * Get list of member`s groups
	 * @param string $username
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public static function getUserGroups($username) {
		if (empty($username) || !is_string($username)) {
			throw new InvalidArgumentException('Invalid username parameter');
		}

		$filter = "(&(objectCategory=person)(samaccountname={$username}))";
		$result = self::instance()->select(self::PATH_INTERNAL, $filter, array("memberof"));
		$users = self::getUsersFromRecord($result);

		if (empty($users) || !isset($users[0]['groups'])) {
			return [];
		}

		if (!is_array($users[0]['groups'])) {
			$users[0]['groups'] = [$users[0]['groups']];
		}

		$groups = [];

		foreach ($users[0]['groups'] as $group_dn) {
			$parts = explode(',', $group_dn);
			$groups[] = substr($parts[0], 3);
		}

		$securityGroups = self::getSecurityGroups();

		$securityGroupsAsArray = [];

		for ($i = 0; $i < count($securityGroups); $i++) {
			if (!empty($securityGroups[$i]['name'])) {
				$securityGroupsAsArray[$i]=$securityGroups[$i]['name'];
			} else {
				$securityGroupsAsArray[$i]=null;
			}
		}

		//TODO. Need clear internal security groups
		$groups = array_intersect($groups, $securityGroupsAsArray);

		return $groups;
	}

	/**
	 * Create new user in activeDirectory
	 *
	 * @param array $userData [
	 *    'username',
	 *    'first_name',
	 *    'last_name',
	 *    'office_name',
	 *    'department_name',
	 *    'phone_number',
	 *    'date_birth',
	 *    'title',
	 *    'brand',
	 * ]
	 *
	 * @return bool
	 * @throws InvalidArgumentException
	 */
	public static function createUser(array $userData) {
		$allowedParameters = array_values(self::$renamingLdapAttributes);

		foreach ($allowedParameters as $key) {
			if (!isset($userData[$key])) {
				throw new InvalidArgumentException("Invalid user data property '{$key}'.");
			}
		}

		$offices = self::getOffices();

		if (!in_array($userData['office_name'], $offices)) {
			throw new InvalidArgumentException("The office name '{$userData['office_name']}' is absent.");
		}

		$departments = self::getDepartments();

		if (!in_array($userData['department_name'], $departments)) {
			throw new InvalidArgumentException("The office name '{$userData['department_name']}' is absent.");
		}

		$attributes = array_keys(self::$renamingLdapAttributes);
		$ldapUserData = [];

		foreach ($attributes as $attribute) {
			$key = self::$renamingLdapAttributes[$attribute]['name'];

			if (!empty($userData[$key])) {
				$ldapUserData[$attribute] = $userData[$key];
			}
		}

		$ldapUserData['objectclass'] = ['top', 'person', 'organizationalPerson', 'user'];
		$ldapUserData['userprincipalname'] = $userData['username'] . '@' . self::COMPANY_NAME;
		$ldapUserData['cn'] = $ldapUserData['displayname'] = $userData['username'];

		$dn = 'CN=:username,OU=Users,OU=:office,:path';
		$dn = strtr($dn, array(
			':username' => $userData['username'],
			':office' => $userData['office_name'],
			':path' => self::PATH_INTERNAL,
		));

		return (self::instance()->add($dn, $ldapUserData));
	}

	/**
	 * Update user information in activeDirectory
	 *
	 * @param string $username
	 * @param array $userData
	 * @return bool
	 */
	public static function updateUser($username, array $userData) {
		$allowed_parameters = array_values(self::$renamingLdapAttributes);
		$ldapData = [];

		foreach (self::$renamingLdapAttributes as $ldapAttribute => $attribute) {
			if (!isset($userData[$attribute]) || !in_array($attribute, $allowed_parameters)) {
				continue;
			}

			$ldapData[$ldapAttribute] = $userData[$attribute];
		}

		return (self::instance()->modify($username, $ldapData));
	}

	/**
	 * Remove user from group
	 *
	 * @param string $username
	 * @param string $groupName
	 */
	public static function deleteUserFromGroup($username, $groupName) {
		self::instance()->moveUser($username, $groupName, false);
	}

	/**
	 * Add user to group
	 *
	 * @param string $username
	 * @param string $groupName
	 */
	public static function addUserToGroup($username, $groupName) {
		self::instance()->moveUser($username, $groupName, true);
	}

	/**
	 * Collect users from response
	 * @param string $record
	 * @return array
	 */
	private static function getUsersFromRecord($record) {
		$totalUsers = [];

		if (isset($record['OU'])) {
			foreach ($record['OU'] as $data) {
				$users = self::getUsersFromRecord($data);
				$totalUsers = array_merge($totalUsers, $users);
			}
		}

		if (isset($record['CN'])) {
			$users = [];

			foreach ($record['CN'] as $userData) {
				$user = [];

				if (isset($userData['memberof'])) {
					$user['groups'] = $userData['memberof'];
				} else {
					foreach (self::$renamingLdapAttributes as $ldapKey => $value) {
						$user[$value] = isset($userData[$ldapKey]) ? $userData[$ldapKey] : null;
					}

					$user['dn'] = $userData['dn'];
				}

				$users[] = $user;
			}

			$totalUsers = array_merge($totalUsers, $users);
		}

		return $totalUsers;
	}

	/**
	 * Proccesing a list of groups from record
	 *
	 * @param array $record
	 *
	 * @return array
	 */
	private static function getGroupsFromRecord($record) {
		$totalGroups = [];

		if (isset($record['OU'])) {
			foreach ($record['OU'] as $data) {
				$groups = self::getGroupsFromRecord($data);
				$totalGroups = array_merge($totalGroups, $groups);
			}
		}

		if (isset($record['CN'])) {
			$groups = array();

			foreach ($record['CN'] as $groupData) {
				$groups[] = array(
					'name' => $groupData['name'],
					'dn' => $groupData['dn'],
				);
			}

			$totalGroups = array_merge($totalGroups, $groups);
		}

		return $totalGroups;
	}

	/**
	 * Get instance Singletone
	 * @return activeDirectory
	 */
	private static function instance() {
		if (!isset(self::$instance)) {
			self::$instance = new self(self::$settings);
			$result = self::$instance->createConnection();

			if ($result !== true) {
				throw new RuntimeException('Connection error');
			}
		}

		return self::$instance;
	}

	/**
	 * Class`s constructor
	 */
	protected function __construct($settings) {
		$this->settings = $settings;
	}

	/**
	 * Getter class
	 *
	 * @param $name
	 * @return mixed
	 */
	public function __get($name) {
		switch ($name) {
			case 'hostname':
			case 'port':
			case 'domain':
			case 'username':
			case 'password':
				return isset($this->settings[$name]) ? $this->settings[$name] : null;
			default:
				throw new InvalidArgumentException("Invalid property '{$name}'");
		}
	}

	/**
	 * Create LDAP connection
	 *
	 * @return string | true
	 */
	protected function createConnection() {
		if (!$connection = @ldap_connect($this->hostname, $this->port)) {
			return 'Could not connect to LDAP server.';
		}

		ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

		if (!@ldap_bind($connection, $this->username . '@' . $this->domain, $this->password)) {
			return 'LDAP bind error: ' . ldap_error($connection);
		}

		$this->connection = $connection;

		return true;
	}

	/**
	 * Make request to the Active Directory server
	 *
	 * @param string $base_dn
	 * @param string $filter
	 * @param array $attributes
	 * @param string $sort_by
	 * @throw @RuntimeException
	 *
	 * @return array
	 */
	protected function select($base_dn, $filter, array $attributes = array(), $sort_by = null) {
		$result = ldap_search($this->connection, $base_dn, $filter, $attributes);

		if ($result === false) {
			throw new RuntimeException('Ldap search error');
		}

		if (!is_null($sort_by)) {
			if (!ldap_sort($this->connection, $result, $sort_by)) {
				throw new RuntimeException('Ldap sort error');
			}
		}

		$answer = ldap_get_entries($this->connection, $result);

		if ($answer === false) {
			throw new RuntimeException('Ldap get entries error');
		}

		$answer = $this->parseAnswer($answer, $attributes);

		return $answer;
	}

	/**
	 * Update user in Active Directory
	 *
	 * @param string $currentUsername
	 * @param array $attributes
	 * @return bool
	 */
	protected function modify($currentUsername, array $attributes) {
		$dn = self::instance()->getUserDn($currentUsername);

		if (!ldap_modify($this->connection, $dn, $attributes)) {
			return false;
		}

		return  true;
	}

	/**
	 * Ldap add
	 *
	 * @param string $dn
	 * @param array $attributes
	 * @return bool
	 */
	protected function add($dn, array $attributes) {
		if (!ldap_add($this->connection, $dn, $attributes)) {
			return false;
		}

		return true;
	}

	/**
	 * Add | Remove user from group
	 *
	 * @param string $username
	 * @param string $groupName
	 * @param bool $isAdd - add or remove
	 */
	private function moveUser($username, $groupName, $isAdd) {
		$groups = self::getUserGroups($username);
		$inGroup = in_array($groupName, $groups);

		if (($isAdd  && $inGroup) || (!$isAdd && !$inGroup)) {
			return;
		}

		$adObj = self::instance();

		$adObj->moveUserToGroup(
			$adObj->getGroupDn($groupName),
			[
				'user_dn' => $adObj->getUserDn($username),
				'username' => $username,
				'group_name' => $groupName
			],
			$isAdd
		);
	}


	/**
	 * Changed Active Directory record
	 *
	 * @param string $groupDn
	 * @param array $attribute
	 * @param bool $isAdd
	 * @throw RuntimeException
	 */
	protected function moveUserToGroup($groupDn, $attribute, $isAdd) {
		if ($isAdd) {
			$result = ldap_mod_add($this->connection, $groupDn, ['member' => $attribute["user_dn"]]);
		} else {
			$result = ldap_mod_del($this->connection, $groupDn, ['member' => $attribute["user_dn"]]);
		}

		if (!$result) {
			throw new RuntimeException('Cannot change user`s group');
		}
	}

	/**
	 * Get full user DN string by username
	 * @param string $username
	 * @return string
	 * @throws InvalidArgumentException, RuntimeException
	 */
	private function getUserDn($username) {
		if (empty($username) || !is_string($username)) {
			throw new InvalidArgumentException('Invelid username parameter');
		}

		$filter = "(&(objectCategory=User)(samaccountname={$username}))";
		$result = self::instance()->select(self::PATH_INTERNAL, $filter, ['useraccountcontrol']);
		$users = self::getUsersFromRecord($result);

		if (empty($users)) {
			throw new RuntimeException("User with username '{$username}' not found.");
		}

		if (empty($users[0]['dn'])) {
			throw new RuntimeException("User with username '{$username}' has empty DN attribute.");
		}

		return $users[0]['dn'];
	}

	/**
	 * Get Active Directory path of group by group name
	 *
	 * @param $groupName
	 * @return string
	 * @throws InvalidArgumentException, RuntimeException
	 */
	private function getGroupDn($groupName) {
		if (empty($groupName) || !is_string($groupName)) {
			throw new InvalidArgumentException('Invalid group name parameter');
		}

		$filter = "(&(objectCategory=Group)(name={$groupName}))";
		$result = self::instance()->select(self::PATH_GROUPS, $filter, ['name']);
		$groups = self::getGroupsFromRecord($result);

		if (empty($groups)) {
			throw new RuntimeException("Group with name '{$groupName}' not found.");
		}

		if (empty($groups[0]['dn'])) {
			throw new RuntimeException("Group with name '{$groupName}' has empty DN attribute.");
		}

		return $groups[0]['dn'];
	}

	/**
	 * Parse LDAP answer
	 * @param array $answer
	 * @param array $attributes
	 * @return array
	 */
	private function parseAnswer(array $answer, array $attributes = []) {
		$result = [];

		if (!isset($answer['count']) || !is_int($answer['count'])) {
			throw new RuntimeException("Invalid LDAP answer format");
		}

		for ($i = 0; $i < $answer['count']; $i++) {
			if (!isset($answer[$i]['dn']) || !is_string($answer[$i]['dn'])) {
				throw new RuntimeException("Invalid LDAP answer format");
			}

			foreach ($attributes as $attribute) {
				if (isset($answer[$i][$attribute])) {
					if (!isset($answer[$i][$attribute]['count']) || !is_int($answer[$i][$attribute]['count'])) {
						throw new RuntimeException("Invalid LDAP answer format");
					}

					if ($answer[$i][$attribute]['count'] == 1) {
						if (!is_scalar($answer[$i][$attribute][0])) {
							throw new RuntimeException("Invalid LDAP answer format");
						}

						$result[$attribute] = $answer[$i][$attribute][0];
					} else {
						$result[$attribute] = [];

						for ($j = 0; $j < $answer[$i][$attribute]['count']; $j++) {
							if (!is_scalar($answer[$i][$attribute][$j])) {
								throw new RuntimeException("Invalid LDAP answer format");
							}

							$result[$attribute][] = $answer[$i][$attribute][$j];
						}
					}
				} else {
					$result[$attribute] = null;
				}
			}

			$result['dn'] = $answer[$i]['dn'];
		}

		return $result;
	}
}
