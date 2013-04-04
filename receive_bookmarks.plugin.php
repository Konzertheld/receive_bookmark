<?php
namespace Habari;

class ReceiveBookmarks extends Plugin
{
	/**
	 * Create very simple config form to make the bookmarklet available
	 */
	public function configure()
	{
		$form = new FormUI(__CLASS__);
		// Looks awful but is just Javascript with all the line breaks stripped
		$bookmarklet = 'javascript:(function(){var go=function(path,params){var openWindow=window.open(path);var form=openWindow.document.createElement("form");form.setAttribute("method","post");form.setAttribute("action",path);for(var key in params){var f=document.createElement("input");f.setAttribute("type","hidden");f.setAttribute("name",key);f.setAttribute("value",params[key]);form.appendChild(f);}openWindow.document.body.appendChild(form);form.submit();};var t=prompt("Enter comma-separated tags","");go("' . Site::get_url('site') . '/receive_bookmark",{bookmark_url:encodeURIComponent(location.href), tags:encodeURIComponent(t), title:encodeURIComponent(document.title)});})()';
		
		$form->append(FormControlStatic::create("bookmarklet_link")->set_static("<a href='" . $bookmarklet . "'>Bookmarklet</a>"));
		return $form;
	}
	
	/**
	 * Add a rewrite rule to catch incoming links
	 */
	public function filter_rewrite_rules($rules)
	{
		$rules[] = RewriteRule::create_url_rule('"receive_bookmark"', 'PluginHandler', 'receive_bookmark');
		return $rules;
	}
	
	/**
	 * Handle incoming links
	 * This is the function that gets executed when the above rewrite rule matches
	 */
	public function action_plugin_act_receive_bookmark($handler)
	{
		// Save the values we just received
		// Do it only if there really is a value. If the user was redirected, the POST data will be gone
		// Check the annoying way because PHP 5.4 requires it
		$title = $_POST->raw("title");
		if(isset($title)) {
			Session::add_to_set("new_bookmark", urldecode($title), "title");
		}
		$tags = $_POST->raw("tags");
		if(isset($tags)) {
			Session::add_to_set("new_bookmark", urldecode($tags), "tags");
		}
		$bookmark_url = $_POST->raw("bookmark_url");
		if(isset($bookmark_url)) {
			Session::add_to_set("new_bookmark", urldecode($bookmark_url), "bookmark_url");
		}

		if(User::identify()->loggedin) {
			$data = Session::get_set("new_bookmark");
			$params = array("title" => $data["title"],
				"user_id" => User::identify()->id,
				"status" => Post::status('published'),
				"tags" => $data["tags"],
				"content_type" => Post::type('link') // @todo this should be optional
			);
			$post = Post::create($params);
			$post->info->url = $data["bookmark_url"];
			// It would be really, really cool if we could grab the date from the source. Maybe if the source has an "alternate" atom view? Though we should store it separate then to keep the time the link was saved.
			$post->publish();
			
			Utils::redirect(URL::get('admin', 'page=publish&id=' . $post->id));
		}
		else {
			Session::notice(_t("You have to login to save a bookmark", __CLASS__));
			Utils::redirect(URL::get('auth', array('page' => 'login')));
		}
	}
	
	/**
	 * Send the user through our incoming-bookmark-handler above after he logged in
	 */
	public function filter_login_redirect_dest($login_dest, $user, $login_session)
	{
		// Do not redirect when there is no data (aka normal logins)
		$data = Session::get_set("new_bookmark", false);
		if(isset($data)) {
			Utils::redirect( Site::get_url('site') . '/receive_bookmark');
		}
	}
}
?>