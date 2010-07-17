<h3>Dropping tables...</h3>
<?php
	try {db::query(null, "drop table glusers")->execute();			} catch (Exception $e) {}
	try {db::query(null, "drop table glprofiles")->execute();		} catch (Exception $e) {}
	try {db::query(null, "drop table glposts")->execute();			} catch (Exception $e) {}
	try {db::query(null, "drop table glcategories")->execute();		} catch (Exception $e) {}
	try {db::query(null, "drop table glcategory2posts")->execute();	} catch (Exception $e) {}
?>
...ok

<h3>Creating tables...</h3>
<?php 
	try {
		db::query(null, "create table glusers (id integer auto_increment, login varchar(31), password varchar(31), primary key(id))")->execute();
		db::query(null, "create table glprofiles (id integer auto_increment, email varchar(255), primary key(id))")->execute();
		db::query(null, "create table glposts (id integer auto_increment, content text, user_id integer, primary key(id))")->execute();
		db::query(null, "create table glcategories (id integer auto_increment, name varchar(63), primary key(id))")->execute();
		db::query(null, "create table glcategory2posts (category_id integer, post_id integer, primary key(category_id, post_id))")->execute();
		db::query(null, "create index glcategory2posts_post_id on glcategory2posts (post_id)")->execute();
		db::query(null, "create index glposts_user_id on glposts (user_id)")->execute();
	} catch(Exception $e) { echo "Failed with exception : " . $e->getMessage();	return;	}
?>
...ok

<h3>Creating objects...</h3>
<?php
	echo "Creating user Jane...";
	try {
		$jane = glue::create('gluser', array('login' => 'jane', 'password' => 'qsdf'));
	} catch(Exception $e) { echo "Failed with exception : " . $e->getMessage();	return;	}
	echo "ok<br/>";
	
	echo "Checking object properties...";
	if ($jane->login === 'jane')
		echo 'ok';
	else { 
		echo 'failed';
		return;
	}
?>

<h3>Inserting objects...</h3>
<?php 
	echo "Testing mass insertion...";
	try {
		$jane = glue::create('gluser', array('login' => 'jane', 'password' => 'qsdf'));
		$john = glue::create('gluser', array('login' => 'john',  'password' => 'azer'));
		glue::set($jane, $john)->insert();
	} catch(Exception $e) { echo "Failed with exception : " . $e->getMessage();	return;	}
	$count = db::query(Database::SELECT, "select * from glusers")->execute()->count();
	if ($count === 2)
		echo "ok<br/>";
	else { 
		echo 'failed';
		return;
	}
	
	echo "Checking ids after insertion...";
	if (isset($jane->id))
		echo "ok<br/>";
	else { 
		echo 'failed';
		return;
	}
	
	echo "Testing AR-like insertion...";
	//try {
		$profile1 = glue::create('glprofile', array('id' => $jane->id, 'email' => "jane@gmail.com"));
		$profile2 = glue::create('glprofile', array('id' => $john->id, 'email' => "john@gmail.com"));
		$profile1->insert();
		$profile2->insert();
	//} catch(Exception $e) { echo "Failed with exception : " . $e->getMessage();	return;	}
	$count = db::query(Database::SELECT, "select * from glprofiles")->execute()->count();
	if ($count === 2)
		echo "ok<br/>";
	else { 
		echo 'failed';
		return;
	}
		
	// Inserting required data for tests to come :
	$post1 = glue::create('glpost', array('content' => "jane's post 1", 'user_id' => $jane->id));
	$post2 = glue::create('glpost', array('content' => "jane's post 2", 'user_id' => $jane->id));
	$post3 = glue::create('glpost', array('content' => "john's post 1", 'user_id' => $john->id));
	$post4 = glue::create('glpost', array('content' => "john's post 2", 'user_id' => $john->id));
	glue::set($post1, $post2, $post3, $post4)->insert();
	
	$biology = glue::create('glcategory', array('name' => "biology"));
	$geology = glue::create('glcategory', array('name' => "geology"));
	$history = glue::create('glcategory', array('name' => "history"));
	glue::set($biology, $geology, $history)->insert();
	
	glue::set(
	        glue::create('glcategory2post', array('post_id' => $post1->id, 'category_id' => $biology->id)),
	        glue::create('glcategory2post', array('post_id' => $post1->id, 'category_id' => $geology->id)),
	        glue::create('glcategory2post', array('post_id' => $post2->id, 'category_id' => $biology->id)),
	        glue::create('glcategory2post', array('post_id' => $post3->id, 'category_id' => $biology->id)),
	        glue::create('glcategory2post', array('post_id' => $post3->id, 'category_id' => $geology->id)),
	        glue::create('glcategory2post', array('post_id' => $post3->id, 'category_id' => $history->id))
		)->insert();
	
	<h3>Updating objects...</h3>
?>



















