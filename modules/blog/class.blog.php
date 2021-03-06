<?php
/* ========================================================================
 * Open eClass 3.0
* E-learning and Course Management System
* ========================================================================
* Copyright 2003-2014  Greek Universities Network - GUnet
* A full copyright notice can be read in "/info/copyright.txt".
* For a full list of contributors, see "credits.txt".
*
* Open eClass is an open platform distributed in the hope that it will
* be useful (without any warranty), under the terms of the GNU (General
		* Public License) as published by the Free Software Foundation.
* The full license can be read in "/info/license/license_gpl.txt".
*
* Contact address: GUnet Asynchronous eLearning Group,
*                  Network Operations Center, University of Athens,
*                  Panepistimiopolis Ilissia, 15784, Athens, Greece
*                  e-mail: info@openeclass.org
* ======================================================================== */

/**
 * This class represents a Blog
 */
Class Blog {
    private $course_id;
    private $user_id;
    
    /**
     * Constructor
     * @param course_id the id of the course in case of a course blog
     * @param user_id the id of the user in case of a user blog
     */
    public function __construct($course_id, $user_id) {
        if ($course_id != 0) {//course blog
            $this->course_id = $course_id;
            $this->user_id = 0;
        } else {//user blog
            $this->user_id = $user_id;
            $this->course_id = 0;
        }
    }
    
    /**
     * Get the number of blog posts in a blog
     * @return int
     */
    public function blogPostsNumber() {
        $sql = 'SELECT COUNT(`id`) as c FROM `blog_post` WHERE ';
        if ($this->course_id != 0) {//course blog
            $sql .= '`course_id` = ?d';
            $param = $this->course_id;
        } else {//user blog
            $sql .= '`course_id` = 0 AND `user_id` = ?d';
            $param = $this->user_id;
        }
        $numPosts = Database::get()->querySingle($sql, $param)->c;
        return $numPosts;
    }
    
    /**
     * Get blog posts from DB with pagination
     * @param page the page of the blog
     * @param postsPerPage the number of blog posts per page
     * @return array with blog post objects
     */
    public function getBlogPostsDB($page, $postsPerPage) {
        $offset = $page*$postsPerPage;
        $sql = 'SELECT * FROM `blog_post` WHERE ';
        if ($this->course_id != 0) {//course blog
            $sql .= '`course_id` = ?d ORDER BY `time` DESC LIMIT ?d,?d';
            $param = $this->course_id;
        } else {//user blog
            $sql .= '`course_id` = 0 AND `user_id` = ?d ORDER BY `time` DESC LIMIT ?d,?d';
            $param = $this->user_id;
        }
        $result = Database::get()->queryArray($sql, $param, $offset, $postsPerPage);
        $ret = array();
        if (is_array($result)) {
        	$ret = BlogPost::loadFromPDOArr($result);
        }
        return $ret;
    }
    
    /**
     * Get the most popular blog posts in a blog from DB
     * @param num the number of blog posts to get
     * @return array with blog post objects
     */
    private function getPopularBlogPostsDB($num) {
        $sql = 'SELECT * FROM `blog_post` WHERE ';
        if ($this->course_id != 0) {//course blog
        	$sql .= '`course_id` = ?d ORDER BY `views` DESC LIMIT ?d';
        	$param = $this->course_id;
        } else {//user blog
        	$sql .= '`course_id` = 0 AND `user_id` = ?d ORDER BY `views` DESC LIMIT ?d';
        	$param = $this->user_id;
        }
        $result = Database::get()->queryArray($sql, $param, $num);
        $ret = array();
        if (is_array($result)) {
            $ret = BlogPost::loadFromPDOArr($result);
        }
        return $ret;
    }
    
    /**
     * HTML code for the most popular blog posts
     * @param num the number of blog posts to show
     * @return string HMTL code
     */
    public function popularBlogPostsHTML($num) {
        global $course_code, $langBlogPopular;
        $posts = $this->getPopularBlogPostsDB($num);
        $out = "<table><tr><th style=\"border:0px;\">$langBlogPopular</th></tr>";
        foreach ($posts as $post) {
            $out .= "<tr><td><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;action=showPost&amp;pId=".$post->getId()."'>".q($post->getTitle())."</a></td></tr>";
        }
        $out .= "</table><br>";
        return $out;
    }
    
    /**
     * HTML code for the navigation links
     * @param page the number of blog page show
     * @param postsPerPage the number of posts per page
     * @return string HMTL code
     */
    public function navLinksHTML($page, $postsPerPage) {
        global $course_code, $langBlogNewerPosts, $langBlogOlderPosts;
        
        $num_posts = $this->blogPostsNumber();
        
        if ($page < ceil($num_posts/$postsPerPage)-1) {
        	$older = TRUE;
        } elseif ($page == ceil($num_posts/$postsPerPage)-1) {
        	$older = FALSE;
        }
         
        if($page > 0)
        	$newer = TRUE;
        $out = '';
        if ((isset($newer) && $newer) || (isset($older) && $older)) {
            $out = "<table id='navcontainer' width='100%'><tr>";
            if(isset($newer) && $newer)
            	$out .= "<td class='left'><a href='$_SERVER[PHP_SELF]?course=".$course_code."&amp;action=showBlog&amp;page=".($page-1)."'>".$langBlogNewerPosts."</a>&nbsp;</td>";
            if(isset($older) && $older)
            	$out .= "<td class='right'><a href='$_SERVER[PHP_SELF]?course=".$course_code."&amp;action=showBlog&amp;page=".($page+1)."'>".$langBlogOlderPosts."</a>&nbsp;</td>";
            $out .= "</tr></table>";
        }
        
        return $out;
    }
    
    /**
     * HTML code for the chronological tree of blog posts
     * @param tree_month the month of the most recent blog post
     * @param tree_year the year of the most recent blog post
     * @return string HMTL code
     */
    public function chronologicalTreeHTML($tree_month, $tree_year) {
        global $course_id, $course_code, $langBlogPostHistory, $langMonthNames;
        $out = '';
        
        if ($this->blogPostsNumber()>0) {
            $sql = "SELECT `id`, `title`, YEAR(`time`) as `y`, MONTH(`time`) as `m`, DAY(`time`) as `d` FROM `blog_post` WHERE course_id = ?d ORDER BY `time` DESC";
            $result = Database::get()->queryArray($sql, $course_id);
            
            $tree = array();
            
            $out .= "<div id='indentmenu'><b>$langBlogPostHistory</b><ul class='blog_tree'>";
            //chronological array
            foreach ($result as $obj) {
                $tree[$obj->y][$obj->m][$obj->id] = $obj->title;
            }
            
            foreach ($tree as $year => $yearard) {
                $count_month = 0;
                $out_m="";
                foreach ($yearard as $month => $monthard) {
            	    $count_month += count($monthard);
            	    $count_id = 0;
            
            	    $out_p="";
                    foreach ($monthard as $id => $title) {
                    	$count_id += 1;
                    	$out_p .= "<li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;action=showPost&amp;pId=".$id."'>".q($title)."</a></li>";
            	    }
                    $m = $langMonthNames['long'][$month-1];
                    if ($month == $tree_month && $year == $tree_year) {
                        $out_m .= "<li><a href='javascript:void(0)' onclick='toggleMenu(\"m".$month."y".$year."\")'>".$m." (".$count_id.")</a>";
                        $out_m .= "<ul class='blog_tree_m_cur' id=\"m".$month."y".$year."\">".$out_p."</ul></li>";
                    } else {
                        $out_m .= "<li><a href='javascript:void(0)' onclick='toggleMenu(\"m".$month."y".$year."\")'>".$m." (".$count_id.")</a>";
                        $out_m .= "<ul class='blog_tree_m' id=\"m".$month."y".$year."\">".$out_p."</ul></li>";
                    }
                }
                if ($year == $tree_year){
                	$out .= "<li><a href='javascript:void(0)' onclick='toggleMenu(\"y".$year."\")'>".$year." (".$count_month.")</a>";
                	$out .= "<ul class='blog_tree_y_cur' id=\"y".$year."\">".$out_m."</ul></li>";
                }
                else{
                	$out .= "<li><a href='javascript:void(0)' onclick='toggleMenu(\"y".$year."\")'>".$year." (".$count_month.")</a>";
                	$out .= "<ul class='blog_tree_y' id=\"y".$year."\">".$out_m."</ul></li>";
                }
            }
            $out .= "</ul></div>";
        }
        
        return $out;
    }
    
    /**
     * Get blog course id
     * @return int, 0 if a user's blog
     */
    public function getCourse() {
    	return $this->course_id;
    }
    
    /**
     * Get blog user id
     * @return int, 0 if a course's blog
     */
    public function getUser() {
    	return $this->user_id;
    }
    
    /**
     * Check if a user has permission to create blog posts
     * @param isEditor boolean showing if user is teacher
     * @param studConfigVal boolean based on the config value allowing users to create posts
     * @param uid the user id
     * @return boolean
     */
    public function permCreate($isEditor, $studConfigVal, $uid) {
        if ($isEditor) {//teacher is always allowed to create
            return true;
        } else {
            if ($studConfigVal) {//students allowed to create
                $sql = "SELECT COUNT(`user_id`) as c FROM `course_user` WHERE `course_id` = ?d AND `user_id` = ?d";
                $result = Database::get()->querySingle($sql, $this->course_id, $uid);
                if ($result->c > 0) {//user is course member
                	return true;
                } else {//user is not course member
                	return false;
                }
            } else {//students are not allowed to create
                return false;
            }
        }
    }
}
