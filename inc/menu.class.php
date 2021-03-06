<?php


/**
 * Class PluginMydashboardMenu
 */
class PluginAclcategoryMenu extends CommonGLPI {
   /**
    * Will contain an array indexed with classnames, each element of this array<br>
    * will be an array containing widgetId s
    * @var array of array of string
    */
   private $widgets    = [];
   private $widgetlist = [];
   /**
    * Will contain an array of strings with js function needed to add a widget
    * @var array of string
    */
   private $addfunction = [];
   /**
    * User id, most of the time it will correspond to currently connected user id,
    * but sometimes it will correspond to the DEFAULD_ID, for the default dashboard
    * @var int
    */
   private $users_id;
   /**
    * An array of string, each string is a widgetId of a widget that must be added on the mydashboard
    * @var array of string
    */
   private $dashboard = [];
   /**
    * An array of string indexed by classnames, each string is a statistic (time /mem)
    * @var array of string
    */
   private $stats = [];
   /**
    * A string to store infos, those infos are displayed in the top right corner of the mydashboard
    * @var string
    */
   //Unused
   //private $infos = "";
   public static  $ALL_VIEW                = -1;
   public static  $CHANGE_VIEW             = 3;
   public static  $PROBLEM_VIEW            = 2;
   public static  $TICKET_VIEW             = 1;
   public static  $RSS_VIEW                = 7;
   public static  $GLOBAL_VIEW             = 6;
   public static  $GROUP_VIEW              = 4;
   public static  $MY_VIEW                 = 5;
   public static  $PROJECT_VIEW            = 8;
   public static  $ASSET_VIEW              = 9;
   private static $DEFAULT_ID              = 0;
   private static $_PLUGIN_MYDASHBOARD_CFG = [];

   static $rightname = "plugin_aclcategory";

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {
      return __('ACL Category', 'aclcategory');
   }

   /**
    * PluginMydashboardMenu constructor.
    *
    * @param bool $show_all
    */
   function __construct($show_all = false) {
      
   }


   /**
    * Show dashboard
    *
    * @param int $users_id
    * @param int $active_profile
    *
    * @return FALSE if the user haven't the right to see Dashboard
    * @internal param type $user_id
    */
   public function showMenu($users_id = -1, $active_profile = -1, $predefined_grid = 0) {

      Html::requireJs('mydashboard');
      //We check the wanted interface (this param is later transmitted to PluginMydashboardUserWidget to get the dashboard for the user in this interface)
      $this->interface = (Session::getCurrentInterface() == 'central') ? 1 : 0;

      // validation des droits
      if (!Session::haveRightsOr("plugin_mydashboard", [CREATE, READ])) {
         return false;
      }
      // checking if no users_id is specified
      $this->users_id = Session::getLoginUserID();
      if ($users_id != -1) {
         $this->users_id = $users_id;
      }

      //Now the mydashboard
      $this->showDashboard($active_profile, $predefined_grid);

   }


   /**
    * Dropdown profiles which have rights under the active one
    *
    * @param $options array of possible options:
    *    - name : string / name of the select (default is profiles_id)
    *    - value : integer / preselected value (default 0)
    *
    **/
   static function dropdownProfiles($options = []) {
      global $DB;

      $p['name']  = 'profiles_id';
      $p['value'] = '';
      $p['rand']  = mt_rand();

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $query = "SELECT `glpi_profiles`.`name`, `glpi_profiles`.`id`
                FROM `glpi_profiles` 
                LEFT JOIN `glpi_profilerights` ON (`glpi_profilerights`.`profiles_id` = `glpi_profiles`.`id`)" .
               Profile::getUnderActiveProfileRestrictRequest("WHERE") . "
                AND `glpi_profilerights`.`name` = 'plugin_mydashboard'
                AND `glpi_profilerights`.`rights` > 0
                ORDER BY `glpi_profiles`.`name`";

      $res = $DB->query($query);

      //New rule -> get the next free ranking
      if ($DB->numrows($res)) {
         while ($data = $DB->fetch_assoc($res)) {
            $profiles[$data['id']] = $data['name'];
         }
      }
      Dropdown::showFromArray($p['name'], $profiles,
                              ['value'               => $p['value'],
                               'rand'                => $p['rand'],
                               'display_emptychoice' => true,
                               'on_change'           => 'this.form.submit()']);
   }

   /**
    * This method shows the widget list (in the left part) AND the mydashboard
    *
    * @param int $selected_profile
    */
   private function showDashboard($selected_profile = -1, $predefined_grid = 0) {

      //If we want to display the widget list menu, we have to 'echo' it, else we also need to call it because it initialises $this->widgets (link between classnames and widgetId s)
      //      $_SESSION['plugin_mydashboard_editmode'] = false;
      $edit = PluginMydashboardPreference::checkEditMode(Session::getLoginUserID());
      if ($edit > 0) {
         echo $this->getWidgetsList($selected_profile, $edit);
      }

      //Now we have a widget list menu, but, it does nothing, we have to bind
      //list item click with the adding on the mydashboard, and we need to display
      //this div contains the header and the content (basically the ul used by sDashboard)

      echo "<div class='plugin_mydashboard_dashboard' >";//(div.plugin_mydashboard_dashboard)

      //This first div is the header of the mydashboard, basically it display a name, informations and a button to toggle full screen
      echo "<div class='plugin_mydashboard_header'>";//(div.plugin_mydashboard_header)

      $this->displayEditMode($edit, $selected_profile, $predefined_grid);
      //      echo "</span>";//end(span.plugin_mydashboard_header_title)
      echo "<span class='plugin_mydashboard_header_right'> ";//(span.plugin_mydashboard_header_right)
      //If administator enabled fullscreen we display the button to toggle fullscreen
      //(maybe we could also only add the js when needed, but jquery is loaded so would be only foolproof)
      if (self::$_PLUGIN_MYDASHBOARD_CFG['enable_fullscreen']
          && $edit < 1
          && $this->interface == 1) {
         echo "<i class=\"fa fa-arrows-alt plugin_mydashboard_header_fullscreen header_fullscreen plugin_mydashboard_discret \" alt='" . __("Fullscreen", "mydashboard") . "' title='" . __("Fullscreen", "mydashboard") . "'></i>";
      }

      echo "</span>";//end(span.plugin_mydashboard_header_right)
      echo "</div>";//end(div.plugin_mydashboard_header)
      //Now the content
      echo "<div class='plugin_mydashboard_content'>";//(div.plugin_mydashboard_content)

      echo "</div>";//end(div.plugin_mydashboard_content)
      echo "</div>";//end(div.plugin_mydashboard_dashboard)

      //      //Automatic refreshing of the widgets (that wants to be refreshed -> see PluginMydashboardModule::toggleRefresh() )
      if (self::$_PLUGIN_MYDASHBOARD_CFG['automatic_refresh']) {
         //We need some javascript, here are scripts (script which have to be dynamically called)
         $refreshIntervalMs = 60000 * self::$_PLUGIN_MYDASHBOARD_CFG['automatic_refresh_delay'];
         //this js function call itself every $refreshIntervalMs ms, each execution result in the refreshing of all refreshable widgets

         echo Html::scriptBlock('
            function automaticRefreshAll(delay) {
                 setInterval(function () {
                     mydashboard.refreshAll();
                 }, delay);
             }
            function refreshAll() {
                 $(\'.refresh-icon\').trigger(\'click\');
             };');

         echo Html::scriptBlock('
               automaticRefreshAll(' . $refreshIntervalMs . ');
         ');

      }
   }

   function displayEditMode($edit = 0, $selected_profile = -1, $predefined_grid = 0) {

      if ($this->interface == 1) {

         $drag = PluginMydashboardPreference::checkDragMode(Session::getLoginUserID());

         if ($edit > 0) {

            echo "<form id=\"editmode\" class='plugin_mydashboard_header_title' method='post' 
                     action='" . $this->getSearchURL() . "' onsubmit='return true;'>";

            echo __('Edit mode', 'mydashboard');

            if (!Session::haveRight("plugin_mydashboard_config", CREATE) && $edit == 2) {
               $edit = 1;
            }
            if ($edit == 2) {
               echo "&nbsp;(" . __('Global', 'mydashboard') . ")&nbsp;";
            }
            echo "&nbsp;:&nbsp;";

            if (Session::haveRight("plugin_mydashboard_config", CREATE) && $edit == 2) {
               self::dropdownProfiles(['value' => $selected_profile]);
            } else {
               echo Html::hidden("profiles_id", ['value' => $_SESSION['glpiactiveprofile']['id']]);
            }

            echo "&nbsp;<span class='plugin_mydashboard_add_button'><a id='add-widget' href='#'>" . __('Add a widget', 'mydashboard') . "</a></span>";//(span.plugin_mydashboard_header_title)

            echo "&nbsp;<i class=\"fa fa-caret-down\"></i></span>";

            echo "&nbsp;";
            echo __('Load a predefined grid', 'mydashboard') . "&nbsp;<i class='fa fa-tasks fa-1x'></i>";
            echo "<span class='sr-only'>" . __('Load a predefined grid', 'mydashboard') . "</span>";
            echo "&nbsp;";

            $elements = PluginMydashboardDashboard::getPredefinedDashboardName();

            Dropdown::showFromArray("predefined_grid", $elements, [
               'value'               => $predefined_grid,
               'width'               => '170px',
               'display_emptychoice' => true,
               'on_change'           => 'this.form.submit()']);
            echo "&nbsp;";

            if ($edit == 1) {
               echo "&nbsp;";
               echo "<a id='save-grid' href='#' title=\"" . __('Save grid', 'mydashboard') . "\">";
               echo __('Save grid', 'mydashboard') . "</a>&nbsp;<i class='fa fa-floppy-o fa-1x'></i>";
               echo "<span class='sr-only'>" . __('Save grid', 'mydashboard') . "</span>";
               echo "&nbsp;";
            }
            if (Session::haveRight("plugin_mydashboard_config", CREATE) && $edit == 2) {
               echo "&nbsp;";
               echo "<a id='save-default-grid' href='#' title=\"" . __('Save default grid', 'mydashboard') . "\">";
               echo __('Save default grid', 'mydashboard') . "</a>&nbsp;<i class='fa fa-hdd-o fa-1x'></i>";
               echo "<span class='sr-only'>" . __('Save default grid', 'mydashboard') . "</span>";
               echo "&nbsp;";
            }

            echo "&nbsp;";
            echo "<a id='clear-grid' href='#' title=\"" . __('Clear grid', 'mydashboard') . "\">";
            echo __('Clear grid', 'mydashboard') . "</a>&nbsp;<i class='fa fa-window-restore  fa-1x'></i>";
            echo "<span class='sr-only'>" . __('Clear grid', 'mydashboard') . "</span>";
            echo "&nbsp;";

            echo "&nbsp;";
            echo "<a id='close-edit' href='#' title=\"" . __('Close edit mode', 'mydashboard') . "\">";
            echo __('Close edit mode', 'mydashboard') . "</a>&nbsp;<i class='fa fa-times-circle-o fa-1x'></i>";
            echo "<span class='sr-only'>" . __('Close edit mode', 'mydashboard') . "</span>";
            echo "&nbsp;";
            Html::closeForm();

            echo "<div class='bt-alert bt-alert-success' id='success-alert'>
                <strong>" . __('Success', 'mydashboard') . "</strong> - 
                " . __('The widget was added to dashboard. Save the dashboard to see it.', 'mydashboard') . "
            </div>";
            echo Html::scriptBlock('
               $("#success-alert").hide();
         ');

            echo "<div class='bt-alert bt-alert-error' id='error-alert'>
                <strong>" . __('Error', 'mydashboard') . "</strong>
                " . __('Please reload your page.', 'mydashboard') . "
            </div>";
            echo Html::scriptBlock('
               $("#error-alert").hide();
         ');

         } else {
            echo "<a id='edit-grid' href='#' title=\"" . __('Switch to edit mode', 'mydashboard') . "\">";
            echo "<i class='plugin_mydashboard_discret plugin_mydashboard_header_editmode fa fa-pencil-square-o fa-2x'></i>";
            echo "<span class='sr-only'>" . __('Switch to edit mode', 'mydashboard') . "</span>";
            echo "</a>";

            if ($drag < 1) {
               echo "<a id='drag-grid' href='#' title=\"" . __('Permit drag / resize widgets', 'mydashboard') . "\">";
               echo "<i class='plugin_mydashboard_discret plugin_mydashboard_header_editmode fa fa-lock fa-2x'></i>";
               echo "<span class='sr-only'>" . __('Permit drag / resize widgets', 'mydashboard') . "</span>";
               echo "</a>";
            }
            if ($drag > 0) {

               echo "<a id='undrag-grid' href='#' title=\"" . __('Block drag / resize widgets', 'mydashboard') . "\">";
               echo "<i class='plugin_mydashboard_discret plugin_mydashboard_header_editmode fa fa-unlock-alt fa-2x'></i>";
               echo "<span class='sr-only'>" . __('Block drag / resize widgets', 'mydashboard') . "</span>";
               echo "</a>";

               echo "<a id='save-grid' href='#' title=\"" . __('Save positions', 'mydashboard') . "\">";
               echo "<i class='plugin_mydashboard_discret plugin_mydashboard_header_editmode fa fa-floppy-o fa-2x'></i>";
               echo "<span class='sr-only'>" . __('Save positions', 'mydashboard') . "</span>";
               echo "</a>";
            }
            if (Session::haveRight("plugin_mydashboard_config", CREATE)) {
               echo "<a id='edit-default-grid' href='#' title=\"" . __('Custom and save default grid', 'mydashboard') . "\">";
               echo "<i class='plugin_mydashboard_discret plugin_mydashboard_header_editmode fa fa-cogs fa-2x'></i>";
               echo "<span class='sr-only'>" . __('Custom and save default grid', 'mydashboard') . "</span>";
               echo "</a>";
            }
         }
      }
   }

   /**
    * Initialization of widgets at installation
    */
   static function installWidgets() {

      $list       = new PluginMydashboardWidgetlist();
      $widgetlist = $list->getList(false);

      $widgetDB = new PluginMydashboardWidget();

      $widgetclasses = $widgetlist['GLPI'];

      foreach ($widgetclasses as $widgetclass => $widgets) {
         foreach ($widgets as $widgetview => $widgetlist) {
            foreach ($widgetlist as $widgetId => $widgetTitle) {
               if (is_numeric($widgetId)) {
                  $widgetId = $widgetTitle;
               }
               $widgetDB->saveWidget($widgetId);

            }
         }
      }
   }

   /**
    * Stores every widgets in Database (see PluginMydashboardWidget)
    */
   private function initDBWidgets() {
      $widgetDB    = new PluginMydashboardWidget();
      $widgetsinDB = getAllDatasFromTable(PluginMydashboardWidget::getTable());

      $widgetsnames = [];
      foreach ($widgetsinDB as $widget) {
         $widgetsnames[$widget['name']] = $widget['id'];
      }

      foreach ($this->widgets as $classname => $classwidgets) {
         foreach ($classwidgets as $widgetId => $view) {
            if (!isset($widgetsnames[$widgetId])) {
               $widgetDB->saveWidget($widgetId);
            }
         }
      }
   }


   /**
    * Get the HTML list of the GLPI core widgets available
    *
    * @param array $used
    *
    * @return string, the HTML list
    */
   private function getWidgetsListFromGLPICore($used = []) {
      $wl = "<div class='plugin_mydashboard_menuDashboardListOfPlugin'>";
      $wl .= "<h3 class='plugin_mydashboard_menuDashboardListTitle1'>GLPI</h3>";
      $wl .= "<div class='plugin_mydashboard_menuDashboardListContainer'><ul class=''>";

      //GLPI core classes doesn't display the same thing in each view, we need to provide all views available
      $views = [self::$TICKET_VIEW,
                self::$PROBLEM_VIEW,
                self::$CHANGE_VIEW,
                self::$GROUP_VIEW,
                self::$MY_VIEW,
                self::$GLOBAL_VIEW,
                self::$RSS_VIEW,
                self::$PROJECT_VIEW,
                self::$ASSET_VIEW];
      //To ease navigation we display the name of the view
      $viewsNames = $this->getViewNames();

      $viewContent = [];
      foreach ($views as $view) {
         $viewContent[$view] = "";
      }

      if (!isset($this->widgetlist['GLPI'])) {
         return '';
      }
      $widgetclasses = $this->widgetlist['GLPI'];

      foreach ($widgetclasses as $widgetclass => $widgets) {
         foreach ($widgets as $widgetview => $widgetlist) {
            foreach ($widgetlist as $widgetId => $widgetTitle) {
               if (is_numeric($widgetId)) {
                  $widgetId = $widgetTitle;
               }
               $this->widgets[$widgetclass][$widgetId] = $viewsNames[$widgetview];
               $gsid                                   = PluginMydashboardWidget::getGsID($widgetId);
               if (!in_array($gsid, $used)) {
                  $viewContent[$widgetview] .= "<li "/*."id='btnAddWidgete".$widgetId."'"*/
                                               . " class='plugin_mydashboard_menuDashboardListItem'"
                                               . " data-widgetid='" . $gsid . "'"
                                               . " data-classname='" . $widgetclass . "'"
                                               . " data-view='" . $viewsNames[$widgetview] . "'>";
                  $viewContent[$widgetview] .= $widgetTitle;
                  if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                     $viewContent[$widgetview] .= " (" . $gsid . ")";
                  }
                  $viewContent[$widgetview] .= "</li>\n";
               }
            }
         }
      }
      $is_empty = true;
      //Now we display each group (view) as a list
      foreach ($viewContent as $view => $vContent) {
         if ($vContent != '') {
            $wl .= "<li class='plugin_mydashboard_menuDashboardList'>";
            $wl .= "<h6 class='plugin_mydashboard_menuDashboardListTitle2'>" . $viewsNames[$view] . "</h6>";
            $wl .= "<ul class='plugin_mydashboard_menuDashboardList2'>";

            $wl       .= $vContent;
            $wl       .= "</ul></li>";
            $is_empty = false;
         }
      }

      $wl .= "</ul></div>";
      $wl .= "</div>";
      if ($is_empty) {
         return '';
      } else {
         return $wl;
      }
   }

   /**
    * Get the HTML list of the plugin widgets available
    *
    * @param array $used
    *
    * @return string|boolean
    * @global type $PLUGIN_HOOKS , that's where you have to declare your classes that defines widgets, in
    *    $PLUGIN_HOOKS['mydashboard'][YourPluginName]
    */
   private function getWidgetsListFromPlugins($used = []) {
      $plugin_names = $this->getPluginsNames();
      $wl           = "";
      foreach ($this->widgetlist as $plugin => $widgetclasses) {
         if ($plugin == "GLPI") {
            continue;
         }
         $is_empty = true;
         $tmp      = "<div class='plugin_mydashboard_menuDashboardListOfPlugin'>";
         //
         $tmp .= "<h6 class='plugin_mydashboard_menuDashboardListTitle1'>" . ucfirst($plugin_names[$plugin]) . "</h6>";
         //Every widgets of a plugin are in an accordion (handled by dashboard not the jquery one)
         $tmp .= "<div class='plugin_mydashboard_menuDashboardListContainer'>";
         $tmp .= "<ul>";
         foreach ($widgetclasses as $widgetclass => $widgetlist) {
            $res = $this->getWidgetsListFromWidgetsArray($widgetlist, $widgetclass, 2, $used);
            if (!empty($widgetlist) && $res != '') {
               $tmp      .= $res;
               $is_empty = false;
            }
         }
         $tmp .= "</ul>";
         $tmp .= "</div>";
         $tmp .= "</div>";
         //If there is now widgets available from this plugins we don't display menu entry
         if (!$is_empty) {
            $wl .= $tmp;
         }
      }

      return $wl;
   }


   /**
    * Get all listitems (<li> tags) for an array of widgets ($widgetsarray)
    * In case items of the array ($widgetsarray) is an array of widgets it's recursive
    * It can result as :
    * <li></li>
    * <li><ul>
    *   <li></li>
    *  </ul></li>
    * The class of each li, ul or h3 (title/category), is linked to the javascript for accordion purpose
    * Accordion is only available for level 2, (level 3 and more won't be folded (by default))
    * ATTENTION : it doesn't handle level 1 items (Plugin names, GLPI ...)
    *
    * @param type  $widgetsarray , an arry of widgets (or array of array ... of widgets)
    * @param type  $classname , name of the class containing the widget
    * @param int   $depth
    *
    * @param array $used
    *
    * @return string
    */
   private function getWidgetsListFromWidgetsArray($widgetsarray, $classname, $depth = 2, $used = []) {
      $wl = "";

      foreach ($widgetsarray as $widgetId => $widgetTitle) {
         //We check if this widget is a real widget
         if (!is_array($widgetTitle)) {
            //If no 'title' is specified it won't be 'widgetid' => 'widget Title' but 'widgetid' so
            if (is_numeric($widgetId)) {
               $widgetId = $widgetTitle;
            }
            $this->widgets[$classname][$widgetId] = -1;
            $gsid                                 = PluginMydashboardWidget::getGsID($widgetId);
            if (!in_array($gsid, $used)) {
               $wl .= "<li id='btnAddWidgete" . $widgetId . "'"
                      . " class='plugin_mydashboard_menuDashboardListItem' "
                      . " data-widgetid='" . $gsid . "'"
                      . " data-classname='" . $classname . "'>";
               $wl .= $widgetTitle;
               if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                  $wl .= " (" . $gsid . ")";
               }/*->getWidgetListTitle()*/
               $wl .= "</li>";
            }
         } else { //If it's not a real widget
            //It may/must be an array of widget, in this case we need to go deeper (increase $depth)
            $tmp = "<li class='plugin_mydashboard_menuDashboardList'>";
            $tmp .= "<h6 class='plugin_mydashboard_menuDashboardListTitle$depth'>" . $widgetId . "</h6>";
            $tmp .= "<ul class='plugin_mydashboard_menuDashboardList$depth'>";
            $res = $this->getWidgetsListFromWidgetsArray($widgetTitle, $classname, $depth + 1, $used);
            if ($res != '') {
               $tmp .= $res;
            }
            $tmp .= "</ul></li>";
            if ($res != '') {
               $wl .= $tmp;
            }
         }
      }
      return $wl;
   }

   /**
    * Get an array of widgetNames as ["id1","id2"] for a specifid users_id
    *
    * @param int $id user id
    *
    * @return array of string
    */
   private function getDashboardForUser($id) {
      $user_widget = new PluginMydashboardUserWidget($id, $this->interface);
      return $user_widget->getWidgets();
   }

   /**
    * Get the widget index on dash, to add it in the correct order
    *
    * @param type $name
    *
    * @return int if $name is in self::dash, FALSE otherwise
    */
   private function getIndexOnDash($name) {
      return array_search($name, $this->dashboard);
   }

   /**
    * Get all plugin names of plugin hooked with mydashboard
    * @global type $PLUGIN_HOOKS
    * @return array of string
    */
   private function getPluginsNames() {
      global $PLUGIN_HOOKS;
      $plugins_hooked = $PLUGIN_HOOKS['mydashboard'];
      $tab            = [];
      foreach ($plugins_hooked as $plugin_name => $x) {
         $tab[$plugin_name] = $this->getLocalName($plugin_name);
      }
      return $tab;
   }

   /**
    * Get the translated name of the plugin $plugin_name
    *
    * @param string $plugin_name
    *
    * @return string
    */
   private function getLocalName($plugin_name) {
      $infos = Plugin::getInfo($plugin_name);
      return isset($infos['name']) ? $infos['name'] : $plugin_name;
   }

   /**
    * Display an information in the top left corner of the mydashboard
    *
    * @param type $text
    */
   //    private function displayInfo($text) {
   //        if(is_string($text)) {
   //            $this->infos .= $text;
   //        }
   //    }

   /**
    * Get all languages for a specific library
    *
    * @param $libraryname
    *
    * @return array $languages
    * @internal param string $name name of the library :
    *    Currently available :
    *        sDashboard (for Datatable),
    *        mydashboard (for our own)
    */
   public function getJsLanguages($libraryname) {

      $languages = [];
      switch ($libraryname) {
         case "datatables" :
            $languages['sEmptyTable']     = __('No data available in table', 'mydashboard');
            $languages['sInfo']           = __('Showing _START_ to _END_ of _TOTAL_ entries', 'mydashboard');
            $languages['sInfoEmpty']      = __('Showing 0 to 0 of 0 entries', 'mydashboard');
            $languages['sInfoFiltered']   = __('(filtered from _MAX_ total entries)', 'mydashboard');
            $languages['sInfoPostFix']    = __('');
            $languages['sInfoThousands']  = __(',');
            $languages['sLengthMenu']     = __('Show _MENU_ entries', 'mydashboard');
            $languages['sLoadingRecords'] = __('Loading') . "...";
            $languages['sProcessing']     = __('Processing') . "...";
            $languages['sSearch']         = __('Search') . ":";
            $languages['sZeroRecords']    = __('No matching records found', 'mydashboard');
            $languages['oPaginate']       = [
               'sFirst'    => __('First'),
               'sLast'     => __('Last'),
               'sNext'     => " " . __('Next'),
               'sPrevious' => __('Previous')
            ];
            $languages['oAria']           = [
               'sSortAscending'  => __(': activate to sort column ascending', 'mydashboard'),
               'sSortDescending' => __(': activate to sort column descending', 'mydashboard')
            ];
            $languages['select']          = [
               "rows" => [
                  "_" => "",// __('You have selected %d rows', 'mydashboard')
                  //                  "0" => "Click a row to select",
                  "1" => __('1 row selected', 'mydashboard')
               ]
            ];
            $languages['close']           = __("Close", "mydashboard");
            $languages['maximize']        = __("Maximize", "mydashboard");
            $languages['minimize']        = __("Minimize", "mydashboard");
            $languages['refresh']         = __("Refresh", "mydashboard");
            break;
         case "mydashboard" :
            $languages["dashboardsliderClose"]   = __("Close", "mydashboard");
            $languages["dashboardsliderOpen"]    = __("Dashboard", 'mydashboard');
            $languages["dashboardSaved"]         = __("Dashboard saved", 'mydashboard');
            $languages["dashboardNotSaved"]      = __("Dashboard not saved", 'mydashboard');
            $languages["dataReceived"]           = __("Data received for", 'mydashboard');
            $languages["noDataReceived"]         = __("No data received for", 'mydashboard');
            $languages["refreshAll"]             = __("Updating all widgets", 'mydashboard');
            $languages["widgetAddedOnDashboard"] = __("Widget added on Dashboard", "mydashboard");
            break;
      }
      return $languages;
   }

   /**
    * Get the names of each view
    * @return array of string
    */
   public function getViewNames() {

      $names = [];
      $names[self::$TICKET_VIEW]  = _n('Ticket', 'Tickets', 2);
      $names[self::$PROBLEM_VIEW] = _n('Problem', 'Problems', 2);
      $names[self::$CHANGE_VIEW]  = _n('Change', 'Changes', 2);
      $names[self::$GROUP_VIEW]   = __('Group View');
      $names[self::$MY_VIEW]      = __('Personal View');
      $names[self::$GLOBAL_VIEW]  = __('Global View');
      $names[self::$RSS_VIEW]     = _n('RSS feed', 'RSS feeds', 2);
      $names[self::$PROJECT_VIEW] = _n('Project', 'Projects', 2);
      $names[self::$ASSET_VIEW]   = 'Asset';
      return $names;
   }

   /**
    * Log $msg only when DEBUG_MODE is set
    *
    * @param int $active_profile
    */
   //   private function debug($msg) {
   //      if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
   //         Toolbox::logDebug($msg);
   //      }
   //   }


   /***********************/

   /**
    * @param int $active_profile
    */
   function loadDashboard($active_profile = -1, $predefined_grid = 0) {
      global $CFG_GLPI;

      $this->users_id = Session::getLoginUserID();
      $this->showMenu($this->users_id, $active_profile, $predefined_grid);

      $this->initDBWidgets();
      $grid = [];

      $list = $this->getDashboardForUser($this->users_id);
      $data = [];
      if (count($list) > 0) {
         foreach ($list as $k => $v) {
            $id = PluginMydashboardWidget::getGsID($v);
            if ($id) {
               $data[] = ["id" => $id, "x" => 6, "y" => 6, "width" => 4, "height" => 6];
            }
         }
         $grid = json_encode($data);
      }
      //LOAD WIDGETS
      $edit = PluginMydashboardPreference::checkEditMode(Session::getLoginUserID());
      $drag = PluginMydashboardPreference::checkDragMode(Session::getLoginUserID());
      //WITHOUTH PREFS
      $dashboard     = new PluginMydashboardDashboard();
      $options_users = ["users_id" => Session::getLoginUserID(), "profiles_id" => $active_profile];
      $id_user       = PluginMydashboardDashboard::checkIfPreferenceExists($options_users);

      if ($id_user == 0 || $edit == 2) {
         $options = ["users_id" => 0, "profiles_id" => $active_profile];
         $id      = PluginMydashboardDashboard::checkIfPreferenceExists($options);
         if ($dashboard->getFromDB($id)) {
            $grid = stripslashes($dashboard->fields['grid']);
         }
      }
      //WITH PREFS
      if ($edit != 2) {
         if ($dashboard->getFromDB($id_user)) {
            $grid = stripslashes($dashboard->fields['grid']);
         }
      }
      //LOAD PREDEFINED GRID
      if ($predefined_grid > 0) {
         $grid = PluginMydashboardDashboard::loadPredefinedDashboard($predefined_grid);
      }
      $datagrid = [];
      $datajson = [];
      $optjson  = [];

      if (!empty($grid) && ($datagrid = json_decode($grid, true)) == !null) {

         foreach ($datagrid as $k => $v) {
            if (isset($v["id"])) {
               $datajson[$v["id"]] = PluginMydashboardWidget::getWidget($v["id"]);
            }
         }

         foreach ($datagrid as $k => $v) {
            if (isset($v["id"])) {
               $optjson[$v["id"]] = PluginMydashboardWidget::getWidgetOptions($v["id"]);
            }
         }
      } else {
         echo "<div class='bt-alert bt-alert-warning' id='warning-alert'>
                <strong>" . __('Warning', 'mydashboard') . "!</strong>
                " . __('No widgets founded, please add widgets', 'mydashboard') . "
            </div>";
         echo Html::scriptBlock('$("#warning-alert").fadeTo(2000, 500).slideUp(500, function(){
            $("#success-alert").slideUp(500);
         });');

         $grid = json_encode($grid);
      }
      $datajson = json_encode($datajson);
      $optjson  = json_encode($optjson);

      //FOR ADD NEW WIDGET
      $allwidgetjson = [];

      if ($edit > 0) {
         $widgets = PluginMydashboardWidget::getWidgetList();

         foreach ($widgets as $k => $val) {
            $allwidgetjson[$k] = [__('Save grid to see widget', 'mydashboard')];
            //NOT LOAD ALL WIDGETS FOR PERF
            //            $allwidgetjson[$k] = PluginMydashboardWidget::getWidget($k);
         }
      }
      $allwidgetjson = json_encode($allwidgetjson);
      $msg_delete    = __('Delete widget', 'mydashboard');
      $msg_error     = __('No data available', 'mydashboard');
      $msg_refresh   = __('Refresh widget', 'mydashboard');
      $disableResize = 'true';
      $disableDrag   = 'true';
      $delete_button = 'false';

      if ($this->interface == 1) {
         if ($drag > 0) {
            $disableResize = 'false';
            $disableDrag   = 'false';
         }
         if ($edit > 0) {
            $delete_button = 'true';
            $disableResize = 'false';
            $disableDrag   = 'false';
         }
      }
      echo "<div id='mygrid' class='mygrid'>";
      echo "<div class='grid-stack md-grid-stack'>";
      echo "</div>";

      echo "<script type='text/javascript'>
        $(function () {
            var options = {
                cellHeight: 40,
                verticalMargin: 2,
                 disableResize: $disableResize,
                 disableDrag: $disableDrag,
                 resizable: {
                    handles: 'e, se, s, sw, w'
                }
            };
            $('.grid-stack').gridstack(options);  
            new function () {
                this.serializedData = $grid;
                this.grid = $('.grid-stack').data('gridstack');
                this.loadGrid = function () {
                    this.grid.removeAll();
                    var items = GridStackUI.Utils.sort(this.serializedData);
//                    _.each(items, function (node) {
                     items.forEach(function(node)  {
                         var nodeid = node.id;
                         var optArray = $optjson;
                         var widgetArray = $datajson; 
                         var widget = widgetArray['' + nodeid + ''];
                         if ( widget !== undefined ) {
                            widget = widgetArray['' + nodeid + ''];
                         } else {
                             widget = '$msg_error';
                         }
                         var opt = optArray['' + nodeid + ''];
                         if ( opt !== undefined ) {
                            options = optArray['' + nodeid + ''];
                            if ( options != null ) {
                               refreshopt = optArray['' + nodeid + '']['enableRefresh'];
                            } else {
                                refreshopt = false;
                            }
                         } else {
                             refreshopt = false;
                         }
                         var delbutton = '';
                         var refreshbutton = '';
                         if ($delete_button == 1) {
                            var delbutton = '&nbsp;<button title=\"$msg_delete\" class=\"md-button pull-right\" onclick=\"deleteWidget(\'' + node.id + '\');\"><i class=\"fa fa-times\"></i></button>';
                         }
                         if (refreshopt == 1) {
                            var refreshbutton = '<button title=\"$msg_refresh\" class=\"md-button refresh-icon\" onclick=\"refreshWidget(\'' + node.id + '\');\"><i class=\"fa fa-refresh\"></i></button>';
                         }
                         if ( nodeid !== undefined ) {
                         var el = $('<div><div class=\"grid-stack-item-content md-grid-stack-item-content\">' + refreshbutton + delbutton + widget + '<div/><div/>');
                            this.grid.addWidget(el, node.x, node.y, node.width, node.height, true, null, null, null, null, node.id);
                            }
                    }, this);
                    return false;
                }.bind(this);
                this.saveGrid = function () {
                    this.serializedData = _.map($('.grid-stack > .grid-stack-item:visible'), function (el) {
                        el = $(el);
                        var node = el.data('_gridstack_node');
                        if ( node.id !== undefined ) {
                           return {
                                id: node.id,
                               x: node.x,
                               y: node.y,
                               width: node.width,
                               height: node.height
                           };
                        }
                    }, this);
                    var sData = JSON.stringify(this.serializedData);
                    var profiles_id = -1;
                     $.ajax({
                       url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/saveGrid.php',
                       type: 'POST',
                       data:{data:sData,profiles_id:$active_profile},
                       success:function(data) {
                              window.location.href = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                           }
                       });
                    return false;
                }.bind(this);
                this.saveDefaultGrid = function () {
                    this.serializedData = _.map($('.grid-stack > .grid-stack-item:visible'), function (el) {
                        el = $(el);
                        var node = el.data('_gridstack_node');
                        return {
                             id: node.id,
                            x: node.x,
                            y: node.y,
                            width: node.width,
                            height: node.height
                        };
                    }, this);
                    var sData = JSON.stringify(this.serializedData);
                    var users_id = 0;
                    var profiles_id = -1;
                     $.ajax({
                          url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/saveGrid.php',
                          type: 'POST',
                          data:{data:sData,users_id:users_id,profiles_id:$active_profile},
                          success:function(data) {
                              var redirectUrl = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                             var form = $('<form action=\"' + redirectUrl + '\" method=\"post\">' +
                             '<input type=\"hidden\" name=\"profiles_id\" value=\"$active_profile\"></input>' +
                             '<input type=\"hidden\" name=\"_glpi_csrf_token\" value=\"' + data +'\"></input>'+ 
                            '</form>');
                             $('body').append(form);
                             $(form).submit();
                          }
                       });
                    return false;
                }.bind(this);
                this.clearGrid = function () {
                  $.ajax({
                    url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/clearGrid.php',
                       type: 'POST',
                       success:function(data) {
                              window.location.href = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                           }
                       });
                    return false;
                }.bind(this);
                this.dragGrid = function () {
                  $.ajax({
                    url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/dragGrid.php',
                       type: 'POST',
                       data:{drag_mode:1},
                       success:function(data) {
                              window.location.href = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                          }
                       });
                    return false;
                }.bind(this);
                this.undragGrid = function () {
                  $.ajax({
                    url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/dragGrid.php',
                       type: 'POST',
                       data:{drag_mode:0},
                       success:function(data) {
                              window.location.href = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                          }
                       });
                    return false;
                }.bind(this);
                this.editGrid = function () {
                  $.ajax({
                    url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/editGrid.php',
                       type: 'POST',
                       data:{edit_mode:1},
                       success:function(data) {
                              window.location.href = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                          }
                       });
                    return false;
                }.bind(this);
                this.editDefaultGrid = function () {
                  $.ajax({
                    url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/editGrid.php',
                       type: 'POST',
                       data:{edit_mode:2},
                       success:function(data) {
                              window.location.href = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                          }
                       });
                    return false;
                }.bind(this);
                this.closeEdit = function () {
                  $.ajax({
                    url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/editGrid.php',
                       type: 'POST',
                       data:{edit_mode:0},
                       success:function(data) {
                              window.location.href = '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/front/menu.php';
                          }
                       });
                    return false;
                }.bind(this);
                $('#save-grid').click(this.saveGrid);
                $('#edit-grid').click(this.editGrid);
                $('#drag-grid').click(this.dragGrid);
                $('#undrag-grid').click(this.undragGrid);
                $('#edit-default-grid').click(this.editDefaultGrid);
                $('#close-edit').click(this.closeEdit);
                $('#save-default-grid').click(this.saveDefaultGrid);
                $('#remove-widget').click(this.removewidget);
                $('#clear-grid').click(this.clearGrid);
                this.loadGrid();
            };
        });
        
     
    </script>";
      echo "<script type='text/javascript'>
        $('.header_fullscreen').click(
        function () {
           $('#mygrid').toggleFullScreen();
           $('#mygrid').toggleClass('fullscreen_view');
        });
        function addNewWidget(value) {
             var id = value;
             if (id != 0){
                var widgetArray = $allwidgetjson; 
                widget = widgetArray['' + id + ''];
                var el = $('<div><div class=\"grid-stack-item-content md-grid-stack-item-content\">' +
                         '<button class=\"md-button pull-right\" onclick=\"deleteWidget(\'' + id + '\');\">' +
                          '<i class=\"fa fa-times\"></i></button>' + widget + '<div/><div/>');
                var grid = $('.grid-stack').data('gridstack');
                grid.addWidget(el, 0, 0, 4, 12, '', null, null, null, null, id);
                return true;
             }
             return false;
         };
        function refreshWidget (id) {
            var widgetOptionsObject = [];
            $.ajax({
              url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/refreshWidget.php',
              type: 'POST',
              data:{gsid:id, params:widgetOptionsObject},
              dataType: 'json',
              success:function(data) {
                  var wid = data.id;
                  var wdata = data.widget;
                  var widget = $('div[id='+ wid + ']');
                  widget.replaceWith(wdata);
              }
           });
             return false;
           };
         function refreshWidgetByForm (id, gsid, formId) {
            var widgetOptions = $('#' + formId).serializeArray();
            var widgetOptionsObject = {};
            $.each(widgetOptions,
               function (i, v) {
                   widgetOptionsObject[v.name] = v.value;
               });
            var widget = $('div[id='+ id + ']');
            $.ajax({
              url: '" . $CFG_GLPI['root_doc'] . "/plugins/mydashboard/ajax/refreshWidget.php',
              type: 'POST',
              data:{gsid:gsid, params:widgetOptionsObject,id:id},
              success:function(data) {
                  widget.replaceWith(data);
              }
           });
             return false;
           };
         function deleteWidget (id) {
           this.grid = $('.grid-stack').data('gridstack');
           widget = $('div[data-gs-id='+ id + ']');
//             if (confirm('$msg_delete') == true)
//             { 
                 this.grid.removeWidget(widget);
//             }
             return false;
           };
          
         function downloadGraph(id) {
//             if (!isChartRendered) return; // return if chart not rendered
                html2canvas(document.getElementById(id), {
                 onrendered: function(canvas) {
                     var link = document.createElement('a');
                    link.href = canvas.toDataURL('image/png');
                    
                    if (!HTMLCanvasElement.prototype.toBlob) {
                     Object.defineProperty(HTMLCanvasElement.prototype, 'toBlob', {
                       value: function (callback, type, quality) {
                         var canvas = this;
                         setTimeout(function() {
                           var binStr = atob( canvas.toDataURL(type, quality).split(',')[1] ),
                           len = binStr.length,
                           arr = new Uint8Array(len);
                  
                           for (var i = 0; i < len; i++ ) {
                              arr[i] = binStr.charCodeAt(i);
                           }
                  
                           callback( new Blob( [arr], {type: type || 'image/png'} ) );
                         });
                       }
                    });
                  }
                       
                  canvas.toBlob(function(blob){
                   link.href = URL.createObjectURL(blob);
                   saveAs(blob, 'myChart.png');
                 },'image/png');                      
              }
            })
         }
    </script>";

      echo "</div>";
   }
}

