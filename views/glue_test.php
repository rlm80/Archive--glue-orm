<h3>Dropping tables...</h3>
<?php
	try {db::query(null, "drop table glusers")->execute();			} catch (Exception $e) {}
	try {db::query(null, "drop table glprofiles")->execute();		} catch (Exception $e) {}
	try {db::query(null, "drop table glposts")->execute();			} catch (Exception $e) {}
	try {db::query(null, "drop table glcategories")->execute();		} catch (Exception $e) {}
	try {db::query(null, "drop table glcategory2posts")->execute();	} catch (Exception $e) {}
?>
<p>...OK</p>

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
	} catch(Kohana_Exception $e) {
		echo "Table creation failed with exception : " . $e->getMessage();
		return;
	}
?>
<p>...OK</p>