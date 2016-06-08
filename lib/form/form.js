/**
 * This file contains JS functionality required by mforms and is included automatically
 * when required.
 */

// Namespace for the form bits and bobs
M.form = M.form || {};

if (typeof M.form.dependencyManager === 'undefined') {
    var dependencyManager = function() {
        dependencyManager.superclass.constructor.apply(this, arguments);
    };
    Y.extend(dependencyManager, Y.Base, {
        _locks: null,
        _hides: null,
        _dirty: null,
        _nameCollections : null,
        _fileinputs : null,

        initializer : function() {
            // Setup initial values for complecx properties.
            this._locks = {};
            this._hides = {};
            this._dirty = {};

            // Setup event handlers.
            Y.Object.each(this.get('dependencies'), function(value, i) {
                var elements = this.elementsByName(i);
                elements.each(function(node){
                    var nodeName = node.get('nodeName').toUpperCase();
                    if (nodeName == 'INPUT') {
                        if (node.getAttribute('type').match(/^(button|submit|radio|checkbox)$/)) {
                            node.on('click', this.updateEventDependencies, this);
                        } else {
                            node.on('blur', this.updateEventDependencies, this);
                        }
                        node.on('change', this.updateEventDependencies, this);
                    } else if (nodeName == 'SELECT') {
                        node.on('change', this.updateEventDependencies, this);
                    } else {
                        node.on('click', this.updateEventDependencies, this);
                        node.on('blur', this.updateEventDependencies, this);
                        node.on('change', this.updateEventDependencies, this);
                    }
                }, this);
            }, this);

            // Handle the reset button.
            this.get('form').get('elements').each(function(input){
                if (input.getAttribute('type') == 'reset') {
                    input.on('click', function(){
                        this.get('form').reset();
                        this.updateAllDependencies();
                    }, this);
                }
            }, this);

            this.updateAllDependencies();
        },

        /**
         * Initializes the mapping from element name to YUI NodeList
         */
        initElementsByName : function() {
            var names = {};

            // Collect element names
            Y.Object.each(this.get('dependencies'), function(conditions, i) {
                names[i] = new Y.NodeList();
                for (var condition in conditions) {
                    for (var value in conditions[condition]) {
                        for (var ei in conditions[condition][value]) {
                            names[conditions[condition][value][ei]] = new Y.NodeList();
                        }
                    }
                }
            });

            // Locate elements for each name
            this.get('form').get('elements').each(function(node){
                var name = node.getAttribute('name');
                if (names[name]) {
                    names[name].push(node);
                }
            });
            this._nameCollections = names;
        },
        /**
         * Gets all elements in the form by their name and returns
         * a YUI NodeList
         *
         * @param {string} name The form element name.
         * @return {Y.NodeList}
         */
        elementsByName : function(name) {
            if (!this._nameCollections) {
                this.initElementsByName();
            }
            if (!this._nameCollections[name]) {
                return new Y.NodeList();
            }
            return this._nameCollections[name];
        },
        /**
         * Checks the dependencies the form has an makes any changes to the
         * form that are required.
         *
         * Changes are made by functions title _dependency_{dependencytype}
         * and more can easily be introduced by defining further functions.
         *
         * @param {EventFacade | null} e The event, if any.
         * @param {string} name The form element name to check dependencies against.
         */
        checkDependencies : function(e, dependon) {
            var dependencies = this.get('dependencies'),
                tohide = {},
                tolock = {},
                condition, value, lock, hide,
                checkfunction, result, elements;
            if (!dependencies.hasOwnProperty(dependon)) {
                return true;
            }
            elements = this.elementsByName(dependon);
            for (condition in dependencies[dependon]) {
                for (value in dependencies[dependon][condition]) {
                    checkfunction = '_dependency_'+condition;
                    if (Y.Lang.isFunction(this[checkfunction])) {
                        result = this[checkfunction].apply(this, [elements, value, e]);
                    } else {
                        result = this._dependency_default(elements, value, e);
                    }
                    lock = result.lock || false;
                    hide = result.hide || false;
                    for (var ei in dependencies[dependon][condition][value]) {
                        var eltolock = dependencies[dependon][condition][value][ei];
                        if (tohide.hasOwnProperty(eltolock)) {
                            tohide[eltolock] = tohide[eltolock] || hide;
                        } else {
                            tohide[eltolock] = hide;
                        }

                        if (tolock.hasOwnProperty(eltolock)) {
                            tolock[eltolock] = tolock[eltolock] || lock;
                        } else {
                            tolock[eltolock] = lock;
                        }
                    }
                }
            }

            for (var el in tolock) {
                var needsupdate = false;
                if (!this._locks.hasOwnProperty(el)) {
                    this._locks[el] = {};
                }
                if (tolock.hasOwnProperty(el) && tolock[el]) {
                    if (!this._locks[el].hasOwnProperty(dependon) || this._locks[el][dependon]) {
                        this._locks[el][dependon] = true;
                        needsupdate = true;
                    }
                } else if (this._locks[el].hasOwnProperty(dependon) && this._locks[el][dependon]) {
                    delete this._locks[el][dependon];
                    needsupdate = true;
                }

                if (!this._hides.hasOwnProperty(el)) {
                    this._hides[el] = {};
                }
                if (tohide.hasOwnProperty(el) && tohide[el]) {
                    if (!this._hides[el].hasOwnProperty(dependon) || this._hides[el][dependon]) {
                        this._hides[el][dependon] = true;
                        needsupdate = true;
                    }
                } else if (this._hides[el].hasOwnProperty(dependon) && this._hides[el][dependon]) {
                    delete this._hides[el][dependon];
                    needsupdate = true;
                }

                if (needsupdate) {
                    this._dirty[el] = true;
                }
            }

            return true;
        },
        /**
         * Update all dependencies in form
         */
        updateAllDependencies : function() {
            Y.Object.each(this.get('dependencies'), function(value, name) {
                this.checkDependencies(null, name);
            }, this);

            this.updateForm();
        },
        /**
         * Update dependencies associated with event
         *
         * @param {Event} e The event.
         */
        updateEventDependencies : function(e) {
            var el = e.target.getAttribute('name');
            this.checkDependencies(e, el);
            this.updateForm();
        },
        /**
         * Flush pending changes to the form
         */
        updateForm : function() {
            var el;
            for (el in this._dirty) {
                if (this._locks.hasOwnProperty(el)) {
                    this._disableElement(el, !Y.Object.isEmpty(this._locks[el]));
                }
                if (this._hides.hasOwnProperty(el)) {
                    this._hideElement(el, !Y.Object.isEmpty(this._hides[el]));
                }
            }

            this._dirty = {};
        },
        /**
         * Disables or enables all form elements with the given name
         *
         * @param {string} name The form element name.
         * @param {boolean} disabled True to disable, false to enable.
         */
        _disableElement : function(name, disabled) {
            var els = this.elementsByName(name);
            var filepicker = this.isFilePicker(name);
            els.each(function(node){
                if (disabled) {
                    node.setAttribute('disabled', 'disabled');
                } else {
                    node.removeAttribute('disabled');
                }

                // Extra code to disable filepicker or filemanager form elements
                if (filepicker) {
                    var fitem = node.ancestor('.fitem');
                    if (fitem) {
                        if (disabled){
                            fitem.addClass('disabled');
                        } else {
                            fitem.removeClass('disabled');
                        }
                    }
                }
            });
        },
        /**
         * Hides or shows all form elements with the given name.
         *
         * @param {string} name The form element name.
         * @param {boolean} disabled True to hide, false to show.
         */
        _hideElement : function(name, hidden) {
            var els = this.elementsByName(name);
            els.each(function(node){
                var e = node.ancestor('.fitem');
                if (e) {
                    e.setStyles({
                        display : (hidden)?'none':''
                    });
                }
            });
        },
        /**
         * Is the form element inside a filepicker or filemanager?
         *
         * @param {string} el The form element name.
         * @return {boolean}
         */
        isFilePicker : function(el) {
            if (!this._fileinputs) {
                var fileinputs = {};
                var els = this.get('form').all('.fitem.fitem_ffilepicker input,.fitem.fitem_ffilemanager input');
                els.each(function(node){
                    fileinputs[node.getAttribute('name')] = true;
                });
                this._fileinputs = fileinputs;
            }

            if (this._fileinputs.hasOwnProperty(el)) {
                return this._fileinputs[el] || false;
            }

            return false;
        },
        _dependency_notchecked : function(elements, value) {
            var lock = false;
            elements.each(function(){
                if (this.getAttribute('type').toLowerCase()=='hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                    // This is the hidden input that is part of an advcheckbox.
                    return;
                }
                if (this.getAttribute('type').toLowerCase()=='radio' && this.get('value') != value) {
                    return;
                }
                lock = lock || !Y.Node.getDOMNode(this).checked;
            });
            return {
                lock : lock,
                hide : false
            };
        },
        _dependency_checked : function(elements, value) {
            var lock = false;
            elements.each(function(){
                if (this.getAttribute('type').toLowerCase()=='hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                    // This is the hidden input that is part of an advcheckbox.
                    return;
                }
                if (this.getAttribute('type').toLowerCase()=='radio' && this.get('value') != value) {
                    return;
                }
                lock = lock || Y.Node.getDOMNode(this).checked;
            });
            return {
                lock : lock,
                hide : false
            };
        },
        _dependency_noitemselected : function(elements, value) {
            var lock = false;
            elements.each(function(){
                lock = lock || this.get('selectedIndex') == -1;
            });
            return {
                lock : lock,
                hide : false
            };
        },
        _dependency_eq : function(elements, value) {
            var lock = false;
            var hidden_val = false;
            var options, v, selected, values;
            elements.each(function(){
                if (this.getAttribute('type').toLowerCase()=='radio' && !Y.Node.getDOMNode(this).checked) {
                    return;
                } else if (this.getAttribute('type').toLowerCase() == 'hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                    // This is the hidden input that is part of an advcheckbox.
                    hidden_val = (this.get('value') == value);
                    return;
                } else if (this.getAttribute('type').toLowerCase() == 'checkbox' && !Y.Node.getDOMNode(this).checked) {
                    lock = lock || hidden_val;
                    return;
                }
                if (this.getAttribute('class').toLowerCase() == 'filepickerhidden') {
                    // Check for filepicker status.
                    var elementname = this.getAttribute('name');
                    if (elementname && M.form_filepicker.instances[elementname].fileadded) {
                        lock = false;
                    } else {
                        lock = true;
                    }
                } else if (this.get('nodeName').toUpperCase() === 'SELECT' && this.get('multiple') === true) {
                    // Multiple selects can have one or more value assigned. A pipe (|) is used as a value separator
                    // when multiple values have to be selected at the same time.
                    values = value.split('|');
                    selected = [];
                    options = this.get('options');
                    options.each(function() {
                        if (this.get('selected')) {
                            selected[selected.length] = this.get('value');
                        }
                    });
                    if (selected.length > 0 && selected.length === values.length) {
                        for (var i in selected) {
                            v = selected[i];
                            if (values.indexOf(v) > -1) {
                                lock = true;
                            } else {
                                lock = false;
                                return;
                            }
                        }
                    } else {
                        lock = false;
                    }
                } else {
                    lock = lock || this.get('value') == value;
                }
            });
            return {
                lock : lock,
                hide : false
            };
        },
        /**
         * Lock the given field if the field value is in the given set of values.
         *
         * @param elements
         * @param values
         * @returns {{lock: boolean, hide: boolean}}
         * @private
         */
        _dependency_in : function(elements, values) {
            // A pipe (|) is used as a value separator
            // when multiple values have to be passed on at the same time.
            values = values.split('|');
            var lock = false;
            var hidden_val = false;
            var options, v, selected, value;
            elements.each(function(){
                if (this.getAttribute('type').toLowerCase()=='radio' && !Y.Node.getDOMNode(this).checked) {
                    return;
                } else if (this.getAttribute('type').toLowerCase() == 'hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                    // This is the hidden input that is part of an advcheckbox.
                    hidden_val = (values.indexOf(this.get('value')) > -1);
                    return;
                } else if (this.getAttribute('type').toLowerCase() == 'checkbox' && !Y.Node.getDOMNode(this).checked) {
                    lock = lock || hidden_val;
                    return;
                }
                if (this.getAttribute('class').toLowerCase() == 'filepickerhidden') {
                    // Check for filepicker status.
                    var elementname = this.getAttribute('name');
                    if (elementname && M.form_filepicker.instances[elementname].fileadded) {
                        lock = false;
                    } else {
                        lock = true;
                    }
                } else if (this.get('nodeName').toUpperCase() === 'SELECT' && this.get('multiple') === true) {
                    // Multiple selects can have one or more value assigned.
                    selected = [];
                    options = this.get('options');
                    options.each(function() {
                        if (this.get('selected')) {
                            selected[selected.length] = this.get('value');
                        }
                    });
                    if (selected.length > 0 && selected.length === values.length) {
                        for (var i in selected) {
                            v = selected[i];
                            if (values.indexOf(v) > -1) {
                                lock = true;
                            } else {
                                lock = false;
                                return;
                            }
                        }
                    } else {
                        lock = false;
                    }
                } else {
                    value = this.get('value');
                    lock = lock || (values.indexOf(value) > -1);
                }
            });
            return {
                lock : lock,
                hide : false
            };
        },
        _dependency_hide : function(elements, value) {
            return {
                lock : false,
                hide : true
            };
        },
        _dependency_default : function(elements, value, ev) {
            var lock = false,
                hidden_val = false,
                values
                ;
            elements.each(function(){
                var selected;
                if (this.getAttribute('type').toLowerCase()=='radio' && !Y.Node.getDOMNode(this).checked) {
                    return;
                } else if (this.getAttribute('type').toLowerCase() == 'hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                    // This is the hidden input that is part of an advcheckbox.
                    hidden_val = (this.get('value') != value);
                    return;
                } else if (this.getAttribute('type').toLowerCase() == 'checkbox' && !Y.Node.getDOMNode(this).checked) {
                    lock = lock || hidden_val;
                    return;
                }
                //check for filepicker status
                if (this.getAttribute('class').toLowerCase() == 'filepickerhidden') {
                    var elementname = this.getAttribute('name');
                    if (elementname && M.form_filepicker.instances[elementname].fileadded) {
                        lock = true;
                    } else {
                        lock = false;
                    }
                } else if (this.get('nodeName').toUpperCase() === 'SELECT' && this.get('multiple') === true) {
                    // Multiple selects can have one or more value assigned. A pipe (|) is used as a value separator
                    // when multiple values have to be selected at the same time.
                    values = value.split('|');
                    this.get('options').each(function() {
                        if (this.get('selected')) {
                            selected[selected.length] = this.get('value');
                        }
                    });
                    if (selected.length > 0 && selected.length === values.length) {
                        for (var i in selected) {
                            if (values.indexOf(selected[i]) > -1) {
                                lock = false;
                            } else {
                                lock = true;
                                return;
                            }
                        }
                    } else {
                        lock = true;
                    }
                } else {
                    lock = lock || this.get('value') != value;
                }
            });
            return {
                lock : lock,
                hide : false
            };
        }
    }, {
        NAME: 'mform-dependency-manager',
        ATTRS: {
            form: {
                setter: function(value) {
                    return Y.one('#' + value);
                },
                value: null
            },

            dependencies: {
                value: {}
            }
        }
    });

    M.form.dependencyManager = dependencyManager;
}

/**
 * Stores a list of the dependencyManager for each form on the page.
 */
M.form.dependencyManagers = {};

/**
 * Initialises a manager for a forms dependencies.
 * This should happen once per form.
 */
M.form.initFormDependencies = function(Y, formid, dependencies) {

    // If the dependencies isn't an array or object we don't want to
    // know about it
    if (!Y.Lang.isArray(dependencies) && !Y.Lang.isObject(dependencies)) {
        return false;
    }

    /**
     * Fixes an issue with YUI's processing method of form.elements property
     * in Internet Explorer.
     *     http://yuilibrary.com/projects/yui3/ticket/2528030
     */
    Y.Node.ATTRS.elements = {
        getter: function() {
            return Y.all(new Y.Array(this._node.elements, 0, true));
        }
    };

    M.form.dependencyManagers[formid] = new M.form.dependencyManager({form: formid, dependencies: dependencies});
    return M.form.dependencyManagers[formid];
};

/**
 * Update the state of a form. You need to call this after, for example, changing
 * the state of some of the form input elements in your own code, in order that
 * things like the disableIf state of elements can be updated.
 */
M.form.updateFormState = function(formid) {
    if (formid in M.form.dependencyManagers) {
        M.form.dependencyManagers[formid].updateAllDependencies();
    }
};
