<?php
/**
 *  Quick and dirty tests...not very clean and scientific but if the module
 *  passes all these tests there is a good chance that my last changes didn't
 *  break basic functionality. It's better than nothing.
 *
 * @package    Glue
 * @author     Régis Lemaigre
 * @license    MIT
 */
?>

<?php
	function glue_test_drop_tables() {
		try {db::query(null, "drop table glusers")->execute();				} catch (Exception $e) {}
		try {db::query(null, "drop table glprofiles")->execute();			} catch (Exception $e) {}
		try {db::query(null, "drop table glposts")->execute();				} catch (Exception $e) {}
		try {db::query(null, "drop table glcategories")->execute();			} catch (Exception $e) {}
		try {db::query(null, "drop table glcategory2glposts")->execute();	} catch (Exception $e) {}
	}

	function glue_test_create_tables() {
		db::query(null, "create table glusers (id integer auto_increment, login varchar(31), password varchar(31), primary key(id))")->execute();
		db::query(null, "create table glprofiles (id integer auto_increment, email varchar(255), primary key(id))")->execute();
		db::query(null, "create table glposts (id integer auto_increment, content text, gluser_id integer, primary key(id))")->execute();
		db::query(null, "create table glcategories (id integer auto_increment, name varchar(63), primary key(id))")->execute();
		db::query(null, "create table glcategory2glposts (glcategory_id integer, glpost_id integer, primary key(glcategory_id, glpost_id))")->execute();
		db::query(null, "create index glcategory2glposts_post_id on glcategory2glposts (glpost_id)")->execute();
		db::query(null, "create index glposts_user_id on glposts (gluser_id)")->execute();
	}

	glue_test_drop_tables();
	glue_test_create_tables();

	try {
?>

<h3>Testing entity mappers...</h3>
<?php
	echo "Creating mapper...";
	$mapper = glue::entity('gluser');
	echo "ok<br/>";

	echo "Testing mappers identity map...";
	$mapper2 = glue::entity('gluser');
	if ($mapper === $mapper2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : fields...";
	$prop1 = $mapper->fields();
	sort($prop1);
	$prop1 = array_values($prop1);
	$prop2 = array('id', 'login', 'password');
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : tables...";
	$fields1 = $mapper->tables();
	sort($fields1);
	$fields1 = array_values($fields1);
	$fields2 = array('glusers');
	if ($fields1 == $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : columns...";
	$fields1 = $mapper->columns();
	$fields2 = array ( 'id' => array ( 'glusers' => 'id'), 'login' => array ( 'glusers' => 'login'), 'password' => array ( 'glusers' => 'password'));
	if ($fields1 == $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : properties...";
	$fields1 = $mapper->properties();
	$fields2 = array ( 'id' => 'id', 'login' => 'login', 'password' => 'password');
	if ($fields1 == $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : types...";
	$fields1 = $mapper->types();
	$fields2 = array ( 'id' => 'int', 'login' => 'string', 'password' => 'string');
	if ($fields1 == $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : pk...";
	$fields1 = $mapper->pk();
	sort($fields1);
	$fields1 = array_values($fields1);
	$fields2 = array('id');
	if ($fields1 == $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : fk...";
	$fields1 = $mapper->fk();
	$fields2 = array ( 'id' => 'gluser_id');
	if ($fields1 == $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : autoincrement...";
	$fields1 = $mapper->autoincrement();
	$fields2 = true;
	if ($fields1 === $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : model...";
	$fields1 = $mapper->model();
	$fields2 = 'stdClass';
	if ($fields1 === $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing mapper properties : db...";
	$fields1 = $mapper->db();
	$fields2 = 'default';
	if ($fields1 === $fields2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	//var_dump($fields1); die;
?>

<h3>Testing relationship mappers...</h3>
<?php
	echo "Creating mapper...";
	$mapper = glue::entity('gluser')->relationship('glposts');
	echo "ok<br/>";

	echo "Testing mappers identity map...";
	$mapper2 = glue::entity('gluser')->relationship('glposts');
	if ($mapper === $mapper2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-many properties : type...";
	$mapper = glue::entity('gluser')->relationship('glposts');
	$prop1 = $mapper->type();
	$prop2 = Glue_Relationship::ONE_TO_MANY;
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-many properties : to...";
	$mapper = glue::entity('gluser')->relationship('glposts');
	$prop1 = $mapper->to();
	$prop2 = glue::entity('glpost');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-many properties : from...";
	$mapper = glue::entity('gluser')->relationship('glposts');
	$prop1 = $mapper->from();
	$prop2 = glue::entity('gluser');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-many properties : mapping...";
	$mapper = glue::entity('gluser')->relationship('glposts');
	$prop1 = $mapper->mapping();
	$prop2 = array('gluser.id' => 'glpost.gluser_id');
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-many properties : property...";
	$mapper = glue::entity('gluser')->relationship('glposts');
	$prop1 = $mapper->property();
	$prop2 = 'glposts';
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-one properties : type...";
	$mapper = glue::entity('glpost')->relationship('gluser');
	$prop1 = $mapper->type();
	$prop2 = Glue_Relationship::MANY_TO_ONE;
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-one properties : to...";
	$mapper = glue::entity('glpost')->relationship('gluser');
	$prop1 = $mapper->to();
	$prop2 = glue::entity('gluser');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-one properties : from...";
	$mapper = glue::entity('glpost')->relationship('gluser');
	$prop1 = $mapper->from();
	$prop2 = glue::entity('glpost');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-one properties : mapping...";
	$mapper = glue::entity('glpost')->relationship('gluser');
	$prop1 = $mapper->mapping();
	$prop2 = array('glpost.gluser_id' => 'gluser.id');
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-one properties : property...";
	$mapper = glue::entity('glpost')->relationship('gluser');
	$prop1 = $mapper->property();
	$prop2 = 'gluser';
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-one properties : type...";
	$mapper = glue::entity('gluser')->relationship('glprofile');
	$prop1 = $mapper->type();
	$prop2 = Glue_Relationship::ONE_TO_ONE;
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-one properties : to...";
	$mapper = glue::entity('gluser')->relationship('glprofile');
	$prop1 = $mapper->to();
	$prop2 = glue::entity('glprofile');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-one properties : from...";
	$mapper = glue::entity('gluser')->relationship('glprofile');
	$prop1 = $mapper->from();
	$prop2 = glue::entity('gluser');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-one properties : mapping...";
	$mapper = glue::entity('gluser')->relationship('glprofile');
	$prop1 = $mapper->mapping();
	$prop2 = array('gluser.id' => 'glprofile.id');
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing one-to-one properties : property...";
	$mapper = glue::entity('gluser')->relationship('glprofile');
	$prop1 = $mapper->property();
	$prop2 = 'glprofile';
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-many properties : type...";
	$mapper = glue::entity('glpost')->relationship('glcategories');
	$prop1 = $mapper->type();
	$prop2 = Glue_Relationship::MANY_TO_MANY;
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-many properties : to...";
	$mapper = glue::entity('glpost')->relationship('glcategories');
	$prop1 = $mapper->to();
	$prop2 = glue::entity('glcategory');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-many properties : from...";
	$mapper = glue::entity('glpost')->relationship('glcategories');
	$prop1 = $mapper->from();
	$prop2 = glue::entity('glpost');
	if ($prop1 === $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-many properties : mapping...";
	$mapper = glue::entity('glpost')->relationship('glcategories');
	$prop1 = $mapper->mapping();
	$prop2 = array('glpost.id' => 'glcategory2glpost.glpost_id', 'glcategory2glpost.glcategory_id' => 'glcategory.id');
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing many-to-many properties : property...";
	$mapper = glue::entity('glpost')->relationship('glcategories');
	$prop1 = $mapper->property();
	$prop2 = 'glcategories';
	if ($prop1 == $prop2)
		echo "ok<br/>";
	else
		throw new Exception('failed');
?>

<h3>Creating objects...</h3>
<?php
	echo "Creating user Jane...";
	$jane = glue::create('gluser', array('login' => 'jane', 'password' => 'qsdf'));
	echo "ok<br/>";

	echo "Checking object properties...";
	if ($jane->login === 'jane')
		echo 'ok';
	else
		throw new Exception('failed');
?>

<h3>Inserting objects...</h3>
<?php
	echo "Testing mass insertion...";
	$jane = glue::create('gluser', array('login' => 'jane', 'password' => 'qsdf'));
	$john = glue::create('gluser', array('login' => 'john',  'password' => 'azer'));
	glue::set($jane, $john)->insert();
	$count = db::query(Database::SELECT, "select * from glusers")->execute()->count();
	if ($count === 2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Checking ids after insertion...";
	if (isset($jane->id))
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing AR-like insertion...";
	$profile1 = glue::create('glprofile', array('id' => $jane->id, 'email' => "jane@gmail.com"));
	$profile2 = glue::create('glprofile', array('id' => $john->id, 'email' => "john@gmail.com"));
	$profile1->insert();
	$profile2->insert();
	$count = db::query(Database::SELECT, "select * from glprofiles")->execute()->count();
	if ($count === 2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	// Inserting required data for tests to come :
	$post1 = glue::create('glpost', array('content' => "jane's post 1", 'gluser_id' => $jane->id));
	$post2 = glue::create('glpost', array('content' => "jane's post 2", 'gluser_id' => $jane->id));
	$post3 = glue::create('glpost', array('content' => "john's post 1", 'gluser_id' => $john->id));
	$post4 = glue::create('glpost', array('content' => "john's post 2", 'gluser_id' => $john->id));
	glue::set($post1, $post2, $post3, $post4)->insert();

	$biology = glue::create('glcategory', array('name' => "biology"));
	$geology = glue::create('glcategory', array('name' => "geology"));
	$history = glue::create('glcategory', array('name' => "history"));
	glue::set($biology, $geology, $history)->insert();

	glue::set(
	        glue::create('glcategory2glpost', array('glpost_id' => $post1->id, 'glcategory_id' => $biology->id)),
	        glue::create('glcategory2glpost', array('glpost_id' => $post1->id, 'glcategory_id' => $geology->id)),
	        glue::create('glcategory2glpost', array('glpost_id' => $post2->id, 'glcategory_id' => $biology->id)),
	        glue::create('glcategory2glpost', array('glpost_id' => $post3->id, 'glcategory_id' => $biology->id)),
	        glue::create('glcategory2glpost', array('glpost_id' => $post3->id, 'glcategory_id' => $geology->id)),
	        glue::create('glcategory2glpost', array('glpost_id' => $post3->id, 'glcategory_id' => $history->id))
		)->insert();
?>

<h3>Updating objects...</h3>
<?php
	echo "Testing mass update...";
	$jane->password = 'updated';
	$john->password = 'updated';
	glue::set($jane, $john)->update();
	$count = db::query(Database::SELECT, "select * from glusers where password = 'updated'")->execute()->count();
	if ($count === 2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing AR-like update...";
	$jane->password = 'updated again';
	$jane->update();
	$count = db::query(Database::SELECT, "select * from glusers where password = 'updated again'")->execute()->count();
	if ($count === 1)
		echo "ok<br/>";
	else
		throw new Exception('failed');
?>

<h3>Selecting objects...</h3>
<?php
	echo "Testing execute() return value for queries that return something...";
	$res = glue::select('gluser', $u, array('login' => 'jane'))->execute();
	if (is_object($res) && $res->login = 'jane')
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing execute() return value for queries that return nothing...";
	$res = glue::select('gluser', $u, array('login' => 'no such login'))->execute();
	if ( ! isset($res))
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Testing complex query...";
	$res = glue::select('gluser', $u)
			->with($u, 'glprofile', $pr)
			->with($u, 'glposts', $ps)
			->with($ps, 'glcategories', $ct)
			->execute();
	if (count($u) !== 2 || count($pr) !== 2 || count($ps) !== 4 || count($ct) !== 3) {
		throw new Exception('failed : wrong number of objects in sets');
	}
	if ( ! isset($u[0]->glprofile) ||
		 ! isset($u[0]->glposts) ||
		 ! isset($pr[0]->gluser) ||
		 ! isset($ps[0]->gluser) ||
		 ! isset($ps[0]->glcategories) ||
		 ! isset($ct[0]->glposts) ) {
			throw new Exception('failed : relationships not propertly loaded');
	}
	echo "ok<br/>";

	//echo var_dump($ct->as_array());

?>

<h3>Lazy loading...</h3>
<?php
	glue::clear();
	$jane = glue::select('gluser', $u, array('login' => 'jane'))->fields('id')->execute();

	echo "Lazy loading relationships...";
	if (count($jane->glposts) === 2)
		echo "ok<br/>";
	else
		throw new Exception('failed');

	echo "Lazy loading fields...";
	if ($jane->login === 'jane')
		echo "ok<br/>";
	else
		throw new Exception('failed');

?>

<?php
		glue_test_drop_tables();
	} catch(Exception $e) {
		echo $e->getMessage();
		glue_test_drop_tables();
	}
?>

















