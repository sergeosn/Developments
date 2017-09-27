<?php

require_once(__DIR__ . "/discoveryDns.php");

class discoveryDnsTest extends \PHPUnit_Framework_TestCase  {
	/**
	 * @var discoveryDns
	 */
	private static $discoveryDns;

	/**
	 * Called before run any test in the case
	 */
	public static function setUpBeforeClass() {
		self::$discoveryDns = new discoveryDns();
	}

	/**
	 * Test discoveryDns->generateWord() method
	 *
	 * @param $beginsWith
	 * @param $expected
	 *
	 * @dataProvider dataGenerateWord
	 */
	public function testGenerateWordMethod($beginsWith, $expected) {
		$this->assertEquals($expected, $this->invoke_method(self::$discoveryDns, 'generateWord', [$beginsWith]));
	}

	public function dataGenerateWord() {
		return array(
			array('-', '0'),
			array('--', '-0'),
			array('-a', '-b'),
			array('-djvz', '-djw'),
			array('-djw2zz', '-djw3'),
			array('zz', false),
			array('z', false),
		);
	}

	/**
	 * Test discoveryDns->search() method
	 */
	public function testSearchMethod() {
		$mock = $this->getMockBuilder('discoveryDns')
			->disableOriginalConstructor()
			->setMethods(array('sendPacket'))
			->getMock();

		$mock->method('sendPacket')
			->will($this->returnValueMap($this->generateListZone()));

		$this->assertEquals([
			'--0test.com',
			'--1test.com',
			'--2test.com',
		], $this->invoke_method($mock, 'search', ['-']));
	}

	/**
	 * Test discoveryDns->getAllZones() method
	 */
	public function testGetAllZonesMethod() {
		$mock = $this->getMockBuilder('discoveryDns')
			->disableOriginalConstructor()
			->setMethods(array('sendPacket'))
			->getMock();

		$mock->method('sendPacket')
			->will($this->returnValueMap($this->generateFullListZone()));

		$this->assertEquals([
			'--0test.com',
			'--1test.com',
			'--2test.com',
			'bt0test.com',
			'bu0test.com',
			'bz0test.com',
			'zag.com',
		], $this->invoke_method($mock, 'getAllZones'));
	}

	/**
	 * Function generate one zone
	 * @param string $beginwith
	 * @param int $totalcount
	 * @param array $zonelist
	 *
	 * @return array
	 */
	private function generateZone($beginwith, $totalcount = 0, $zonelist = []) {
		return [
			'zones/?searchNameSearchType=beginsWith&searchName=' . $beginwith,
			'GET',
			[],
			false,
			[
				'zones' => [
					'zoneList' => $zonelist,
					'totalCount' => $totalcount,
				]
			]
		];
	}

	/**
	 * Function generate list of zones by one first symbol `-`
	 *
	 * @return array
	 */
	private function generateListZone() {
		$zone_lists = [
			$this->generateZone('-', 2999),
			$this->generateZone('--', 2000),
			$this->generateZone('---'),
			$this->generateZone('--0', 999, ['--0test.com']),
			$this->generateZone('--1', 999, ['--1test.com']),
			$this->generateZone('--2', 2, ['--2test.com']),
		];

		$zone_lists[] = $this->genereteArray(4, 0, '--');
		$zone_lists[] = $this->genereteArray(1, 0, '-');

		$zone_lists[] = $this->generateZone('b', 1999);
		$zone_lists[] =	$this->generateZone('bt', 999, ['bt0test.com']);
		$zone_lists[] =	$this->generateZone('bu', 999, ['bu0test.com']);
		$zone_lists[] = $this->generateZone('bz', 1, ['bz0test.com']);

		return $zone_lists;
	}

	/**
	 * Function generate full list of zones for the test_get_all_zones_method
	 *
	 * @return array
	 */
	private function generateFullListZone() {
		$zone_lists = $this->generateListZone();
		$zone_lists[] = $this->genereteArray(1, 1);
		$zone_lists[] = $this->generateZone('z', 999, ['zag.com']);
		$zone_lists[] = [
			'zones/',
			'GET',
			[],
			false,
			[
				'zones' => [
					'zoneList' => [],
					'totalCount' => 7,
				]
			]
		];

		return $zone_lists;
	}

	/**
	 * Function generate array of empty zones
	 * @param int $from - start position letter
	 * @param int $to - end position letter
	 * @param string $letters - exist letters
	 *
	 * @return array
	 */
	private function genereteArray($from, $to = 0, $letters = '') {
		$zone_lists = [];
		$allowed_chars = "-0123456789abcdefghijklmnopqrstuvwxyz";

		for ($i = $from; $i <= strlen($allowed_chars) - 1 - $to; $i++) {
			$zone_lists[] = $this->generateZone($letters . $allowed_chars[$i]);
		}

		return $zone_lists;
	}
}
