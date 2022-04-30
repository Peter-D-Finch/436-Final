<?php

/****************************************************************************

Fun Kids Toys PhP / MySQL / Silex Demonstration

This program is designed to demonstrate how to use PhP, MySQL and Silex to 
implement a web application that accesses a database.

Files:  The application is made up of the following files

php: 	index.php - This file has all of the php code in one place.  It is found in 
		the public_html/toystore/ directory of the code source.
		
		connect.php - This file contains the specific information for connecting to the
		database.  It is stored two levels above the index.php file to prevent the db 
		password from being viewable.
		
twig:	The twig files are used to set up templates for the html pages in the application.
		There are 7 twig files:
		- home.twig - home page for the web site
		- footer.twig - common footer for each of he html files
		- header.twig - common header for each of the html files
		- form.html.twig - template for forms html files (login and register)
		- item.html.twig - template for toy information to be displayed
		- orders.html.twig - template for displaying orders made by a customer
		- search.html.twig - template for search results
		
		The twig files are found in the public_html/toystore/views directory of the source code
		
Silex Files:  Composer was used to compose the needed Service Providers from the Silex 
		Framework.  The code created by composer is found in the vendor directory of the
		source code.  This folder should be stored in a directory called toystore that is 
		at the root level of the application.  This code is used by this application and 
		has not been modified.


*****************************************************************************/

// Set time zone  
date_default_timezone_set('America/New_York');

/****************************************************************************   
Silex Setup:
The following code is necessary for one time setup for Silex 
It uses the appropriate services from Silex and Symfony and it
registers the services to the application.
*****************************************************************************/
// Objects we use directly
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Silex\Provider\FormServiceProvider;

// Pull in the Silex code stored in the vendor directory
require_once __DIR__.'/../../silex-files/vendor/autoload.php';

// Create the main application object
$app = new Silex\Application();

// For development, show exceptions in browser
$app['debug'] = true;

// For logging support
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));

// Register validation handler for forms
$app->register(new Silex\Provider\ValidatorServiceProvider());

// Register form handler
$app->register(new FormServiceProvider());

// Register the session service provider for session handling
$app->register(new Silex\Provider\SessionServiceProvider());

// We don't have any translations for our forms, so avoid errors
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
        'translator.messages' => array(),
    ));

// Register the TwigServiceProvider to allow for templating HTML
$app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.path' => __DIR__.'/views',
    ));

// Change the default layout 
// Requires including boostrap.css
$app['twig.form.templates'] = array('bootstrap_3_layout.html.twig');

/*************************************************************************
 Database Connection and Queries:
 The following code creates a function that is used throughout the program
 to query the MySQL database.  This section of code also includes the connection
 to the database.  This connection only has to be done once, and the $db object
 is used by the other code.

*****************************************************************************/
// Function for making queries.  The function requires the database connection
// object, the query string with parameters, and the array of parameters to bind
// in the query.  The function uses PDO prepared query statements.

function queryDB($db, $query, $params) {
    // Silex will catch the exception
    $stmt = $db->prepare($query);
    $results = $stmt->execute($params);
    $selectpos = stripos($query, "select");
    if (($selectpos !== false) && ($selectpos < 6)) {
        $results = $stmt->fetchAll();
    }
    return $results;
}



// Connect to the Database at startup, and let Silex catch errors
$app->before(function () use ($app) {
    include '../../connect.php';
    $app['db'] = $db;
});

/*************************************************************************
 Application Code:
 The following code implements the various functionalities of the application, usually
 through different pages.  Each section uses the Silex $app to set up the variables,
 database queries and forms.  Then it renders the pages using twig.

*****************************************************************************/

// Login Page

$app->match('/login', function (Request $request) use ($app) {
	// Use Silex app to create a form with the specified parameters - username and password
	// Form validation is automatically handled using the constraints specified for each
	// parameter
    $form = $app['form.factory']->createBuilder('form')
        ->add('uname', 'text', array(
            'label' => 'User Name',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('password', 'password', array(
            'label' => 'Password',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('login', 'submit', array('label'=>'Login'))
        ->getForm();
    $form->handleRequest($request);

    // Once the form is validated, get the data from the form and query the database to 
    // verify the username and password are correct
    $msg = '';
    if ($form->isValid()) {
        $db = $app['db'];
        $regform = $form->getData();
        $uname = $regform['uname'];
        $pword = $regform['password'];
        $query = "select password, user_ID 
        			from USERS
        			where username = ?";
        $results = queryDB($db, $query, array($uname));
        # Ensure we only get one entry
        if (sizeof($results) == 1) {
            $retrievedPwd = $results[0][0];
            $cnum = $results[0][1];

            // If the username and password are correct, create a login session for the user
            // The session variables are the username and the customer ID to be used in 
            // other queries for lookup.
            if (password_verify($pword, $retrievedPwd)) {
                $app['session']->set('is_user', true);
                $app['session']->set('user', $uname);
                $app['session']->set('cnum', $cnum);
                return $app->redirect('/toystore/');
            } else {
                $msg = $retrievedPwd;
            }
        }
        else {
        	$msg = 'Invalid User Name or Password - Try again';
        }
        
    }
    // Use the twig form template to display the login page
    return $app['twig']->render('form.html.twig', array(
        'pageTitle' => 'Login',
        'form' => $form->createView(),
        'results' => $msg
    ));
});


// *************************************************************************

// Registration Page

$app->match('/register', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('uname', 'text', array(
            'label' => 'User Name',
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5)))
        ))
        ->add('password', 'repeated', array(
            'type' => 'password',
            'invalid_message' => 'Password and Verify Password must match',
            'first_options'  => array('label' => 'Password'),
            'second_options' => array('label' => 'Verify Password'),    
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5)))
        ))
        ->add('cname', 'text', array(
            'label' => 'Name',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('email', 'text', array(
            'label' => 'Email',
            'constraints' => new Assert\Email()
        ))
        ->add('submit', 'submit', array('label'=>'Register'))
        ->getForm();

    $form->handleRequest($request);

    if ($form->isValid()) {
        $regform = $form->getData();
        $uname = $regform['uname'];
        $pword = $regform['password'];
        $cname = $regform['cname'];
        $email = $regform['email'];
        
        // Check to make sure the username is not already in use
        // If it is, display already in use message
        // If not, hash the password and insert the new customer into the database
        $db = $app['db'];
        $query = "select * from USERS where username = ?";
        $results = queryDB($db, $query, array($uname));
        if ($results) {
    		return $app['twig']->render('form.html.twig', array(
        		'pageTitle' => 'Register',
        		'form' => $form->createView(),
        		'results' => 'Username already exists - Try again'
        	));
        }
        else { 
			$hashed_pword = password_hash($pword, PASSWORD_DEFAULT);
			$insertData = array($cname,$uname,$hashed_pword,$email);
       	 	$query = 'insert into USERS 
        				(name, username, password, email)
        				values (?, ?, ?, ?)';
        	$results = queryDB($db, $query, $insertData);
	        // Maybe already log the user in, if not validating email
        	return $app->redirect('/toystore/');
        }
    }
    return $app['twig']->render('form.html.twig', array(
        'pageTitle' => 'Register',
        'form' => $form->createView(),
        'results' => ''
    ));   
});

// *************************************************************************
 
// Game Result Page

$app->get('/item/{game_ID}', function (Silex\Application $app, $game_ID) {
    // Create query to get the toy with the given toynum
    $db = $app['db'];
    $query = "SELECT v.name as vname, p.name as pname, release_date, reviews, description, genre, url FROM VIDEO_GAME v, PUBLISHERS p WHERE v.pub_ID = p.pub_ID and game_ID = ?";
    $results = queryDB($db, $query, array($game_ID));
    
    // Display results in item page
    return $app['twig']->render('item.html.twig', array(
        'pageTitle' => $results[0]['vname'],
        'results' => $results
    ));
});

// *************************************************************************

// Search Result Page

$app->match('/search', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Search',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query 
        $db = $app['db'];
		$query = "SELECT game_ID, name, release_date, url FROM VIDEO_GAME WHERE name like ?";
		$results = queryDB($db, $query, array('%'.$srch.'%'));
		
        // Display results in search page
        return $app['twig']->render('search.html.twig', array(
            'pageTitle' => 'Search',
            'form' => $form->createView(),
            'results' => $results
        ));
    }
    // If search box is empty, redisplay search page
    return $app['twig']->render('search.html.twig', array(
        'pageTitle' => 'Search',
        'form' => $form->createView(),
        'results' => ''
    ));
});

// *************************************************************************

// *************************************************************************

// DLC Search Result Page

$app->match('/searchDlc', function (Request $request) use ($app) {
    $msg = '';
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Search DLC',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query 
        $db = $app['db'];
		$query = "SELECT d.dlc_name as dname, v.name as vname, p.name as pname, d.release_date as drelease_date FROM DOWNLOADABLE_CONTENT d, VIDEO_GAME v, PUBLISHERS p WHERE d.game_ID = v.game_ID AND p.pub_ID = v.pub_ID AND v.name LIKE ?";
		$results = queryDB($db, $query, array('%'.$srch.'%'));
		
        // Display results in search page
        return $app['twig']->render('searchDlc.html.twig', array(
            'pageTitle' => 'Search DLC',
            'form' => $form->createView(),
            'results' => $results
        ));
        if (sizeof($results) == 0) {
            $msg = 'no results';
        } else {
            $msg = 'results';
        }
    }
    
    // If search box is empty, redisplay search page
    return $app['twig']->render('searchDlc.html.twig', array(
        'pageTitle' => 'Search DLC',
        'form' => $form->createView(),
        'results' => ''
    ));
});

// *************************************************************************

// Genre Search Result Page

$app->match('/genreSearch', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Genre Search',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query 
        $db = $app['db'];
		$query = "SELECT * FROM VIDEO_GAME where genre like ?";
		$results = queryDB($db, $query, array('%'.$srch.'%'));
		
        // Display results in search page
        return $app['twig']->render('genreSearch.html.twig', array(
            'pageTitle' => 'Genre Search',
            'form' => $form->createView(),
            'results' => $results
        ));
    }
    // If search box is empty, redisplay search page
    return $app['twig']->render('genreSearch.html.twig', array(
        'pageTitle' => 'Genre Search',
        'form' => $form->createView(),
        'results' => ''
    ));
});

// *************************************************************************

// Profile

$app->match('/profile', function() use ($app) {
	// Get session variables
	$friends = '';
	$collection = '';
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
		$cnum = $app['session']->get('cnum');
		
		// Look up users friend list
        $db = $app['db'];
		$query = "SELECT u.name, u.username, u.email FROM FRIEND f
            INNER JOIN USERS u ON u.user_ID = f.user_ID1
            WHERE f.user_ID2 = ?
            UNION
            SELECT u.name, u.username, u.email FROM FRIEND f
            INNER JOIN USERS u ON u.user_ID = f.user_ID2
            WHERE f.user_ID1 = ?";
            
        $query2 = 
		$friends = queryDB($db, $query, array($cnum,$cnum));
		
		// Look up users owned games
		$query = "SELECT v.name, v.release_date, v.reviews, v.url FROM OWNED o
            INNER JOIN VIDEO_GAME v ON o.game_ID = v.game_ID
            WHERE o.user_ID = ?";
		$collection = queryDB($db, $query, array($cnum));

	}
	
	return $app['twig']->render('profile.html.twig', array(
		'pageTitle' => 'Profile',
		'pending' => $friends,
		'delivered' => $collection
	));
});
		
// *************************************************************************

// Logout

$app->get('/logout', function () use ($app) {
	$app['session']->clear();
	return $app->redirect('/toystore/');
});
	
// *************************************************************************

// Home Page

$app->get('/', function () use ($app) {
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
	}
	else {
		$user = '';
	}
	return $app['twig']->render('home.twig', array(
        'user' => $user,
        'pageTitle' => 'Home'));
});

// *************************************************************************

// Run the Application

$app->run();