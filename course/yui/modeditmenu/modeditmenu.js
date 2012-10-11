YUI.add('moodle-course-modeditmenu', function(Y) {
    var MODEDITMENUNAME = 'course-modeditmenu';
    var MODEDITMENU = function() {
        MODEDITMENU.superclass.constructor.apply(this, arguments);
    };
    Y.extend(MODEDITMENU, Y.Base, {
        initializer : function() {
            // Catch the page toggle
            Y.one('body').delegate('click', this.toggle_mod_menu, '.block_settings #settingsnav .type_course .modchoosertoggle a', this);
            // Set events to create menu
            Y.one('body').delegate('focus', this.init_menu, '.yui3-menu ul li .section-modedit-link', this);
            Y.one('body').delegate('mouseenter', this.init_menu, '.yui3-menu ul li .section-modedit-link', this);
            Y.one('body').delegate('click', this.init_menu, '.yui3-menu ul li .section-modedit-link', this);
        },
        create_menu : function(menunode) {
            // TODO: Create this from modcommands...
            menunode.plug(Y.Plugin.NodeMenuNav, {mouseOutHideDelay: 10, submenuShowDelay: 500}); 
        },
        init_menu : function (e) {
            // Create menu if not there
            var menunode = e.target.ancestor('.yui3-menu', true, '.modcommandsmenu');
            // If menu found then process
            if (menunode) {
                var submenu = menunode.one('ul li .yui3-menu.yui3-menu-hidden');
                // If submenu there and menu is not create or user press click then process it
                if (submenu) {
                    if (!submenu.hasClass('menu-created')) {
                        e.preventDefault();
                        submenu
                            .addClass('menu-created');
                        this.create_menu(menunode);
                        // Forecfully open submenu as this might not show after creation.
                        menunode.menuNav._showMenu(submenu); 
                    } else if (e.type == 'click') { // If user manage to get this far then show menu.
                        menunode.menuNav._showMenu(submenu);
                    }
                }
            }
        },
        toggle_mod_menu : function (e) {
            // Get the add section link
            var modcommandsmenu = Y.all('span.modcommandsmenu');
            // Get the commands
            var commands = Y.all('span.modcommands');

            if (modcommandsmenu.size() == 0) {
                // Continue with non-js action if there are no modeditmenu
                return;
            }

            // Determine whether they're currently hidden
            if (modcommandsmenu.item(0).hasClass('visibleifjs')) {
                modcommandsmenu
                    .removeClass('visibleifjs')
                    .addClass('hiddenifjs');
                commands
                    .addClass('visibleifjs')
                    .removeClass('hiddenifjs');
            } else {
                modcommandsmenu
                    .addClass('visibleifjs')
                    .removeClass('hiddenifjs');
                commands
                    .removeClass('visibleifjs')
                    .addClass('hiddenifjs');
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