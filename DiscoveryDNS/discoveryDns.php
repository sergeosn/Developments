<?php
/**
 * Class for interaction with the Discovery DNS supplier API.
 *
 * How to use
 *
 * Create new object by domain name:
 * 	$discoveryDns = new discoveryDns();
 *
 * For get zone by domain name:
 * 	$someZone = $discoveryDns->getZoneByDomainName(string $domainName);
 *
 * For get the UUID of the domain name from supplier.
 * 	$uuid = $discoveryDns->getUuidByDomainName(string $domainName);
 *
 * For get zone of from supplier by uuid.
 * 	$someZone = $discoveryDns->getZoneByUuid(string $uuid);
 *
 * Create new zone
 * 	$discoveryDns->createZone(string $domainName, array $record, string $nameServerSetId, string $planId);
 *
 * Update zone
 * 	$discoveryDns->updateZone(string $domainName, array $record);
 *
 * Delete zone
 * 	$discoveryDns->deleteZone(string $domainName);
 *
 * Get all zones from supplier. Notice for the method: Need make full recursive search of data, because supplier API doesn`t supports a pagination.
 * 	$zoneList = discoveryDns->getAllZones();
 *
 *
 * Additional information
 * 	Better use mapping tables in the database, for example `domainName => Uuid`
 *
 */
class discoveryDns {
	/**
	 * @var string Allowed symbols for recursive searching
	 */
	private $allowedChars = "-0123456789abcdefghijklmnopqrstuvwxyz";

	/**
	 * Returns an array of DNS records for the current domain name.
	 *
	 * @param string $domainName
	 * @param array $record [
	 * 	'ip_address',
	 * 	'cname',
	 * 	'txt',
	 * 	'ttl'
	 * ]
	 *
	 * @return array
	 */
	private function getResourceData(string $domainName, array $record) {
		return [
			[
				"name" => $domainName . ".",
				"class" => "IN",
				"ttl" => $record['ttl'],
				"type" => "A",
				"address" => $record['ip_address'],
			],
			[
				"name" => $domainName . ".",
				"class" => "IN",
				"ttl" => $record['ttl'],
				"type" => "AAAA",
				"address" => $record['ip_address'],
			],
			[
				"name" => $domainName . ".",
				"class" => "IN",
				"ttl" => $record['ttl'],
				"type" => "CNAME",
				"target" => $record["cname"],
			],
			[
				"name" => $domainName . ".",
				"class" => "IN",
				"ttl" => $record['ttl'],
				"type" => "TXT",
				"strings" => str_split($record["txt"], 255),
			],
		];
	}

	/**
	 * Logging the API request.
	 *
	 * @param string $uri
	 * @param string $requestMethod
	 * @param array $parameters
	 *
	 * @return int
	 */
	private function logRequest($uri, $requestMethod, array $parameters) {
		/* Need implement logic for logging requests into Database with return value of last insert ID */
		$lastInsertId = 1;

		return $lastInsertId;
	}

	/**
	 * Logging the API response.
	 *
	 * @param int $logId
	 * @param $response
	 *
	 */
	private function logResponse(int $logId, $response) {
		/* Need implement logic for logging responses into Database */
	}

	/**
	 * Method for requests by API.
	 *
	 * @param string $uri
	 * @param string $requestMethod
	 * @param array $parameters
	 *
	 * @throws RuntimeException
	 * @return array
	 */
	private function sendPacket($uri, $requestMethod, array $parameters = []) {
		$logId = $this->logRequest($uri, $requestMethod, $parameters);

		$curl_handle = curl_init("In this place you need put your link from DiscoveryDNS /{$uri}");

		$curl_options = array(
			CURLOPT_SSLCERTTYPE => "PEM",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => $requestMethod,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				"X-Requested-By: SomeCompany",
			),
		);

		$curl_options[CURLOPT_SSLCERT] = "Your SSL certificate";
		$curl_options[CURLOPT_SSLKEY] = "Your SSL key";

		if (!empty($parameters)) {
			$curl_options[CURLOPT_POSTFIELDS] = json_encode($parameters);
		}

		curl_setopt_array($curl_handle, $curl_options);

		$response = curl_exec($curl_handle);

		if (!$response && curl_errno($curl_handle) != 0) {
			$error = curl_errno($curl_handle) . ": " . curl_error($curl_handle);
			$this->logResponse($logId, $error);

			throw new RuntimeException($error);
		}

		$this->logResponse($logId, $response);

		return json_decode($response, true);
	}

	/**
	 * Generate search word for current iteration
	 * @param $beginsWith
	 * @return bool|string
	 */
	private function generateWord($beginsWith) {
		$lastChar = substr($beginsWith, -1);

		if ($lastChar !=  'z') {
			$pos = strpos($this->allowedChars, $lastChar) + 1;
			$beginsWith = substr($beginsWith, 0, -1) . $this->allowedChars[$pos];
		} else {
			if (strlen($beginsWith) <= 2) {
				return false;
			}

			$beginsWithBefore = $beginsWith;

			for ($i = strlen($beginsWith); $i >= 2; $i--) {
				$lastChar = $beginsWith[$i - 2];

				if ($lastChar != 'z') {
					$pos = strpos($this->allowedChars, $lastChar) + 1;
					$beginsWith = substr($beginsWith, 0, $i - 2) . $this->allowedChars[$pos];
					break;
				}
			}

			if ($beginsWithBefore == $beginsWith) {
				$beginsWith = substr($beginsWith, 0, -1);
			}
		}

		return $beginsWith;
	}

	/**
	 * Recursive function for get all zones by API on searching word
	 * @param string $beginsWith
	 * @return array|bool
	 * @throw Exception
	 */
	private function search($beginsWith) {
		$json = $this->sendPacket("zones/?searchNameSearchType=beginsWith&searchName=" . $beginsWith, "GET");
		$result = $json["zones"]["zoneList"];

		if ($json["zones"]["totalCount"] < 1000) { //Count of zones allowed from DiscoveryDNS
			if (strlen($beginsWith) == 1) {
				return $result;
			}

			$beginsWith = $this->generateWord($beginsWith);

			if ($beginsWith !== false) {
				$resultAdditional = $this->search($beginsWith);
			}
		} else {
			$beginsWith .= $this->allowedChars[0];
			$result = $this->search($beginsWith);
		}

		if (!empty($result) && !empty($resultAdditional)) {
			$resultFinal = array_merge($result, $resultAdditional);
		} else if (!empty($result)) {
			$resultFinal = $result;
		} else if (!empty($resultAdditional)) {
			$resultFinal = $resultAdditional;
		} else {
			return false;
		}

		return $resultFinal;
	}

	/**
	 * Validate domain name
	 *
	 * @param string $domainName
	 * @throw InvalidArgumentException
	 */
	private function validateDomainName(string $domainName){
		if (empty($domainName)) {
			throw new InvalidArgumentException('Domain name is empty');
		}

		if (!preg_match("/^(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/", $domainName)) {
			throw new InvalidArgumentException('Invalid domain name');
		}
	}

	/**
	 * Get zone from supplier by domain name.
	 *
	 * @param string $domainName
	 * @return string|bool
	 */
	public function getZoneByDomainName(string $domainName) {
		$json = $this->sendPacket("zones/?searchNameSearchType=exactMatch&searchName=" . $domainName, "GET");

		if (!empty($json["zones"]["zoneList"])) {
			return array_shift($json["zones"]["zoneList"]);
		}

		return false;
	}

	/**
	 * Get the UUID of the domain name from supplier.
	 *
	 * @param string $domainName
	 * @return string|bool
	 */
	public function getUuidByDomainName(string $domainName) {
		$zone = $this->getZoneByDomainName($domainName);

		if ($zone) {
			return $zone['id'];
		}

		return false;
	}

	/**
	 * Get zone of from supplier by uuid.
	 *
	 * @param string $uuid
	 * @return array|boolean
	 */
	public function getZoneByUuid(string $uuid) {
		$json = $this->sendPacket("zones/{$uuid}", "GET");

		if (!empty($json["zone"])) {
			return $json["zone"];
		}

		return false;
	}

	/**
	 * Creates the zone in the supplier.
	 *
	 * @param string $domainName
	 * @param array $record [
	 * 	'ip_address',
	 * 	'cname',
	 * 	'txt'
	 * ]
	 * @param string $nameServerSetId
	 * @param string $planId
	 *
	 * @return boolean
	 */
	public function createZone(string $domainName, array $record, string $nameServerSetId, string $planId) {
		$this->validateDomainName($domainName);

		$parameters = array(
 			"zoneCreate" => array(
				"name" => $domainName,
				"dnssecSigned" => false,
				"brandedNameServers" => false,
				"group" => "mygroup",
				"nameServerSetId" => $nameServerSetId,
        		"planId" => $planId,
				"resourceRecords" => $this->getResourceData($domainName, $record),
			),
		);

		$json = $this->sendPacket("zones", "POST", $parameters);

		return (!empty($json["zone"]));
	}

	/**
	 * Update the zone in the supplier.
	 *
	 * @param string $domainName
	 * @param array $record [
	 * 	'ip_address',
	 * 	'cname',
	 * 	'txt',
	 * 	'ttl'
	 * ]
	 *
	 * @return boolean
	 */
	public function updateZone(string $domainName, array $record) {
		$this->validateDomainName($domainName);

		$data = array(
 			"zoneUpdateResourceRecords" => array(
				"resourceRecords" => $this->getResourceData($domainName, $record),
			),
		);

		$uuid = $this->getUuidByDomainName($domainName);
		$json = '';

		if ($uuid) {
			$json = $this->sendPacket("zones/{$uuid}/resourcerecords", "PUT", $data);
		}

		return (!empty($json["zone"]));
	}

	/**
	 * Delete the zone from the supplier.
	 *
	 * @param string $domainName
	 * @return boolean
	 */
	public function deleteZone(string $domainName) {
		$uuid = $this->getUuidByDomainName($domainName);
		$json = '';

		if ($uuid) {
			$json = $this->sendPacket("zones/{$uuid}", "DELETE");
		}

		// The delete method will return an empty body
		return (empty($json));
	}

	/**
	 * Get all domains from DiscoveryDNS
	 * @return array of arrays
	 * {
	 * 		"@uri",
	 * 		"id",
	 * 		"name",
	 * 		"brandedNameServers",
	 * 		"dnssecSigned",
	 * 		"createDate",
	 * 		"lastUpdateDate",
	 * 		"xfrEnabled"
	 * }
	 *
	 * @throw RuntimeException
	 */
	public function getAllZones() {
		$numChars = strlen($this->allowedChars);
		$zonesList = [];

		try {
			for ($charPos = 0; $charPos <= $numChars - 1; $charPos++) {
				$beginsWith = $this->allowedChars[$charPos];
				$result = $this->search($beginsWith);

				if (empty($result)) {
					continue;
				}

				$zonesList = array_merge($zonesList, $result);
			}
		} catch (Exception $e) {
			$exception = new RuntimeException('DiscoveryDNS. Error with scanning zones', 0, $e);

			throw $exception;
		}

		return $zonesList;
	}
}
