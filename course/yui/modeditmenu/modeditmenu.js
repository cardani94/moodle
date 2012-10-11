YUI.add('moodle-course-modeditmenu', function(Y) {
    var CSS = {
        TOGGLEMODMENU : '.block_settings #settingsnav .type_course .modchoosertoggle a',
        MENULINKICON  : '.yui3-menu ul li .section-modedit-link',
        COMMANDSLINK  : '.commands a',
        COMMANDSECTION: '.mod-indent',
        MODMENUSPAN   : '.modcommandsmenu',
        YUIMENU       : '.yui3-menu',
        YUISUBMENU    : 'ul li .yui3-menu',
        COMMANDSPAN   : '.section .commands',
        VISIBLEIFJS   : 'visibleifjs',
        HIDDENIFJS    : 'hiddenifjs'
    };

    var MODEDITMENUNAME = 'course-modeditmenu';

    var MODEDITMENU = function() {
        MODEDITMENU.superclass.constructor.apply(this, arguments);
    };

    Y.extend(MODEDITMENU, Y.Base, {
        initializer : function() {
            // Catch the page toggle
            Y.one('body').delegate('click', this.toggle_mod_menu, CSS.TOGGLEMODMENU, this);
            // Set events to create menu
            Y.one('body').delegate('focus', this.init_menu, CSS.MENULINKICON, this);
            Y.one('body').delegate('mouseenter', this.init_menu, CSS.MENULINKICON, this);
            Y.one('body').delegate('click', this.init_menu, CSS.MENULINKICON, this);
        },
        create_menu : function(menunode) {
            // Create menu div from existing commands
            var submenunode = Y.Node.create('<div class="yui3-menu yui3-menu-hidden"><div class="yui3-menu-content"><ul></ul></div></div>');
            var commands = menunode.ancestor(CSS.COMMANDSECTION).all(CSS.COMMANDSLINK);
            commands.each(function(commandnode) {
                // Create all links.
                var listitemname = commandnode.getAttribute('title');
                if (!listitemname) {
                    listitemname = commandnode.one('img').getAttribute('alt');
                }
                var listitem = Y.Node.create('<li class="yui3-menuitem"></li>');
                var listitemcontent = commandnode.cloneNode();
                listitemcontent.setHTML(commandnode.getHTML() + '&nbsp;&nbsp;' + listitemname);
                listitem.append(listitemcontent);
                submenunode.one('ul').append(listitem);
            });
            menunode.one('ul li').append(submenunode);

            // Now create menu.
            menunode.plug(Y.Plugin.NodeMenuNav, {mouseOutHideDelay: 10, submenuShowDelay: 500});

            // Hide menu on click, hacky way to hide menu when page is reloading on action
            menunode.menuNav._getTopmostSubmenu(menunode).on('click', function(e) {
                menunode.menuNav._hideAllSubmenus(menunode);
            });
        },
        init_menu : function (e) {
            // Create menu if not there
            var menunode = e.target.ancestor(CSS.YUIMENU, true, CSS.MODMENUSPAN);
            // If menu found then process
            if (menunode) {
                var submenu = menunode.one(CSS.YUISUBMENU);
                // If no submenu then create it.
                if (!submenu) {
                    this.create_menu(menunode);
                    // Simulate event for first time, after menu is created, to have accessible behaviour
                    if (e.type == 'mouseenter') {
                        menunode.menuNav._showMenu(menunode.one(CSS.YUISUBMENU));
                    } else {
                        menunode.simulate(e.type);
                    }
                } else if (e.type == 'click') { // If user manage to get this far then show menu.
                    menunode.menuNav._showMenu(submenu);
                    e.preventDefault();
                }
            }
        },
        toggle_mod_menu : function (e) {
            // Get the add section link
            var modcommandsmenu = Y.all(CSS.MODMENUSPAN);
            // Get the commands
            var commands = Y.all(CSS.COMMANDSPAN);

            if (modcommandsmenu.size() == 0) {
                // Continue with non-js action if there are no modeditmenu
                return;
            }

            // Determine whether they're currently hidden
            if (modcommandsmenu.item(0).hasClass(CSS.VISIBLEIFJS)) {
                modcommandsmenu
                    .removeClass(CSS.VISIBLEIFJS)
                    .addClass(CSS.HIDDENIFJS);
                commands
                    .addClass(CSS.VISIBLEIFJS)
                    .removeClass(CSS.HIDDENIFJS);
            } else {
                modcommandsmenu
                    .addClass(CSS.VISIBLEIFJS)
                    .removeClass(CSS.HIDDENIFJS);
                commands
                    .removeClass(CSS.VISIBLEIFJS)
                    .addClass(CSS.HIDDENIFJS);
            }
        }
    }, {
        NAME : MODEDITMENUNAME,
        ATTRS : {
            aparam : {}
        }
    });
    M.course = M.course || {};

    M.course.init_modeditmenu = function() {
        return new MODEDITMENU();
    }
  }, '@VERSION@', {
      requires:['base', 'node-menunav', 'transition', 'node-event-simulate']
  });