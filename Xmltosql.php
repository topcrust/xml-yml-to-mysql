<?php

class Xmltosql {

	private $pdo;
	private $stm;
	private $settings = array();
	public $sql_count = 0;

	public function __construct($settings) {
		$this->settings = $settings;
	}

	public function db_connect() {
		$this->pdo = new PDO(
			'mysql:host=' . $this->settings['DB_HOST'] . ';dbname=' . $this->settings['DB_NAME'] . ';charset=utf8',
			$this->settings['DB_USER'],
			$this->settings['DB_PASS'],
			[
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES   => FALSE,
			]
		);
	}

	public function db_run($sql, $args = NULL) {
		$this->stm = $this->pdo->prepare($sql);
		$this->stm->execute($args);
		$this->sql_count++;
		return $this->stm;
	}

	public function db_close() {
		$this->stm = NULL;
		$this->pdo = NULL;
	}

	public function get_table_columns($db_table_name) {
		$q = $this->pdo->query("DESCRIBE {$db_table_name}"); # This is only supported by MySQL
		return $q->fetchAll(PDO::FETCH_COLUMN);
	}

	public function build_sql_query($sql_tpl, $db_table_name, $db_table_columns) {
		$sql_cols = array();
		$sql_vals = array();
		$sql_upds = array();
		foreach($db_table_columns as $v) {
			$sql_cols[] = str_replace('%item%', $v, $sql_tpl['sql_col']);
			$sql_vals[] = str_replace('%item%', $v, $sql_tpl['sql_val']);
			if($v !== 'id') $sql_upds[] = str_replace('%item%', $v, $sql_tpl['sql_upd']);
		}
		return strtr($sql_tpl['sql_ins'], array(
			'%db_table_name%' => $db_table_name,
			'%sql_cols%' => implode(',', $sql_cols),
			'%sql_vals%' => implode(',', $sql_vals),
			'%sql_upds%' => implode(',', $sql_upds),
		));
	}

	public function get_last_success_date() {
		return file_exists($this->settings['LOG_FILE_NAME']) ? strtotime( file_get_contents($this->settings['LOG_FILE_NAME']) ) : 0;
	}

	public function get_xml_date() {
		$reader = new XMLReader;
		$reader->open( $this->settings['XML_FILE'] );
		while($reader->read()) {
			if($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'yml_catalog') {
				$xml_date = trim( $reader->getAttribute('date') );
				break;
			}
		}
		$reader->close();
		return strtotime($xml_date);
	}

	public function write_log() {
		file_put_contents($this->settings['LOG_FILE_NAME'], date('Y-m-d H:i:s'));
	}

	public function handle_categories($db_table_name) {
		$sql_tpl = require( __DIR__ . '/' . $db_table_name . '.sql.tpl' );
		$sql_create_db_table = str_replace('%db_table_name%', $db_table_name, $sql_tpl['sql_table']);
		$this->pdo->exec( $sql_create_db_table );
		$db_table_columns = $this->get_table_columns($db_table_name);
		$sql_query = $this->build_sql_query($sql_tpl, $db_table_name, $db_table_columns);
		$this->pdo->beginTransaction();
		$this->stm = $this->pdo->prepare($sql_query);

		$reader = new XMLReader;
		$reader->open( $this->settings['XML_FILE'] );
		while($reader->read() && $reader->name != 'category');
		while($reader->name === 'category') {
			$this->stm->bindValue(':id', (int) $reader->getAttribute('id'), PDO::PARAM_INT);
			$parent_id = (int) $reader->getAttribute('parentId');
			$this->stm->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
			$this->stm->bindValue(':parent_id_upd', $parent_id, PDO::PARAM_INT);
			$name = trim( $reader->readInnerXml() );
			$this->stm->bindValue(':name', $name, PDO::PARAM_STR);
			$this->stm->bindValue(':name_upd', $name, PDO::PARAM_STR);
			$this->stm->execute();
			$reader->next('category');
		}

		$this->pdo->commit();
		$reader->close();
	}

	public function handle_offers($db_table_name) {
		$sql_tpl = require( __DIR__ . '/' . $db_table_name . '.sql.tpl' );
		$sql_create_db_table = str_replace('%db_table_name%', $db_table_name, $sql_tpl['sql_table']);
		$this->pdo->exec( $sql_create_db_table );
		$db_table_columns = $this->get_table_columns($db_table_name);
		$sql_query = $this->build_sql_query($sql_tpl, $db_table_name, $db_table_columns);

		$reader = new XMLReader;
		$reader->open( $this->settings['XML_FILE'] );
		while($reader->read() && $reader->name != 'offer');

		$sql_commit_count = 0;
		$this->pdo->beginTransaction();
		$this->stm = $this->pdo->prepare($sql_query);

		while($reader->name === 'offer') {
			$offer_arr = array(
				'id' => (int) $reader->getAttribute('id'),
				'param' => array(),
				'picture' => array(),
			);
			while(!($reader->name === 'offer' && $reader->nodeType === XMLReader::END_ELEMENT)) {
				$node_name = $reader->name;
				if($reader->nodeType === XMLReader::ELEMENT && in_array($node_name, $db_table_columns)) {
					switch($node_name) {
# TODO
#						case 'delivery-options':
#							break;
						case 'param':
							$key = trim( $reader->getAttribute('name') );
							$offer_arr[$node_name][$key] = trim( $reader->readInnerXml() );
							break;
						case 'picture':
							$offer_arr[$node_name][] = trim( $reader->readInnerXml() );
							break;
						case 'store':
						case 'pickup':
						case 'delivery':
							$offer_arr[$node_name] = (int)( 'true' === strtolower(trim($reader->readInnerXml())) );
#							$offer_arr[$node_name] = (int) filter_var(trim($reader->readInnerXml()), FILTER_VALIDATE_BOOLEAN);
							break;
						default:
							$offer_arr[$node_name] = trim( $reader->readInnerXml() );
					}
				}
				$reader->read();
			}
			$offer_arr['param'] = json_encode($offer_arr['param'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$offer_arr['picture'] = json_encode($offer_arr['picture'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$offer_arr = $offer_arr + array_fill_keys($db_table_columns, NULL);
			foreach($offer_arr as $k => $v) {
				$this->stm->bindValue(":{$k}", $v);
				if($k !== 'id') $this->stm->bindValue(":{$k}_upd", $v);
			}
#			$this->db_run($sql_query, $sql_arr);
			$this->stm->execute();
			$sql_commit_count++;
			if($sql_commit_count > 99) {
				$sql_commit_count = 0;
				$this->pdo->commit();
				$this->pdo->beginTransaction();
				$this->stm = $this->pdo->prepare($sql_query);
			}
			$reader->next('offer');
		}

		if($sql_commit_count > 0) $this->pdo->commit();
		$reader->close();
	}

}

?>