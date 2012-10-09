YUI.add('moodle-course-modeditmenu', function(Y) {
    var MODEDITMENUNAME = 'course-modeditmenu';
    var MODEDITMENU = function() {
        MODEDITMENU.superclass.constructor.apply(this, arguments);
    };
    Y.extend(MODEDITMENU, Y.Base, {
        initializer : function() {
            Y.one('body').delegate('click', this.handle_on_click, '.yui3-menu ul li .section-modedit-link', this);
        },
        create_menu : function(menunode) {
            menunode.plug(Y.Plugin.NodeMenuNav, { autoSubmenuDisplay: false, mouseOutHideDelay: 10 }); 
        },
        handle_on_click : function (e) {
            // Create menu if not there
            var menunode = e.target.ancestor('.yui3-menu', true, '.modcommandsmenu');
            if (menunode) {
                // If menu found then remove accesshide propery
                var submenu = menunode.one('ul li .yui3-menu.yui3-menu-hidden');
                if (submenu) {
                    e.preventDefault();
                    this.create_menu(menunode);
                    submenu.removeClass('yui3-menu-hidden');
                }
            }
        }
    }, {
        NAME : MODEDITMENUNAME,
        ATTRS : {
                 aparam : {}
        }
    });
    M.course = M.course || {};

    M.course.modeditmenu = M.course.modeditmenu || new MODEDITMENU();

    // Toggle menu to be called from modchooser toggle function.
    M.course.modeditmenu.toggle = M.course.modeditmenu.toggle || function(e) {
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
  }, '@VERSION@', {
      requires:['base', 'node-menunav', 'transition', 'node-event-simulate']
  });