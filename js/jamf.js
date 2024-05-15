/**
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of JAMF plugin for GLPI.
 *
 * JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * JAMF plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024-2024 by Teclib'
 * @copyright Copyright (C) 2019-2024 by Curtis Conard
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/jamf
 * -------------------------------------------------------------------------
 */

/* global GLPI_PLUGINS_PATH */
/* global CFG_GLPI */
(function () {
    window.JamfPlugin = function () {
        /**
         * All possible MDM commands for the item on this page (if applicable).
         * @since 1.1.0
         * @type {{}}
         */
        this.commands = {};

        this.dialog_confirm_command = null;

        this.dialog_send_command = null;

        this.jamf_id = -1;

        this.itemtype = null;

        this.items_id = -1;

        /**
         * The AJAX directory.
         * @since 1.1.0
         * @type {string}
         */
        this.ajax_root = '';

        this.init = (args) => {
            if (args !== undefined && args.commands !== undefined) {
                this.commands = args.commands;
                this.jamf_id = args.jamf_id;
                this.itemtype = args.itemtype;
                this.items_id = args.items_id;
                this.ajax_root = args.ajax_root || CFG_GLPI.root_doc + "/" + GLPI_PLUGINS_PATH.jamf + "/ajax/";
            }
        };

        this.onMDMCommandButtonClick = (command, event) => {
            event.preventDefault();
            if (this.commands[command] !== undefined) {
                if (this.commands[command]['params'] !== undefined) {
                    showMDMCommandForm(command);
                } else if (this.commands[command]['confirm'] !== undefined && this.commands[command]['confirm'] === true) {
                    showMDMCommandConfirmation(command);
                } else {
                    this.sendMDMCommand(command);
                }
            }
        };

        /**
         *
         * @param {Object} command
         */
        const showMDMCommandForm = (command) => {
            $.ajax({
                method: 'GET',
                url: (this.ajax_root + "getMDMCommandForm.php"),
                data: {
                    command: command,
                    jamf_id: this.jamf_id,
                    itemtype: this.itemtype,
                    items_id: this.items_id
                }
            }).done((data) => {
                if (data !== undefined && data !== null) {
                    if (this.dialog_send_command !== undefined && this.dialog_send_command !== null) {
                        this.dialog_send_command.remove();
                    }
                    this.dialog_send_command = $(`
<div class="modal" role="dialog">
   <div class="modal-dialog" role="dialog">
       <div class="modal-content">
          <div class="modal-body">`+data+`</div>
          <div class="modal-footer">
             <button type="button" name="close" class="btn btn-secondary" data-dismiss="modal">`+__('Cancel')+`</button>
             <button type="button" name="send" class="btn btn-primary">`+__('Send')+`</button>
          </div>
       </div>
    </div>
</div>`).appendTo('#page');
                    this.dialog_send_command.on('click', 'button[name="send"]', () => {
                        if (this.commands[command]['confirm'] !== undefined && this.commands[command]['confirm'] === true) {
                            showMDMCommandConfirmation(command, this.dialog_send_command.serialize());
                        } else {
                            this.sendMDMCommand(command, this.dialog_send_command.serialize());
                        }
                    });
                    this.dialog_send_command.on('click', 'button[name="close"]', (e) => {
                        $(e.target).closest('.modal').modal('hide');
                    });
                    this.dialog_send_command.modal('show');
                }
            });
        };

        const showMDMCommandConfirmation = (command, params) => {
            if (this.dialog_confirm_command === undefined || this.dialog_confirm_command === null) {
                const warn_text = _x('message', 'Are you sure you want to send the command: %s?', 'jamf').replace("%s", _x('mdm_command', this.commands[command].name, 'jamf'));

                this.dialog_confirm_command = $(`
<div class="modal" role="dialog">
   <div class="modal-dialog" role="dialog">
       <div class="modal-content">
          <div class="modal-body">${warn_text}</div>
          <div class="modal-footer">
             <button type="button" name="close" class="btn btn-secondary" data-dismiss="modal">`+__('Cancel')+`</button>
             <button type="button" name="confirm" class="btn btn-primary">`+__('Confirm')+`</button>
          </div>
       </div>
    </div>
</div>`).appendTo('#page');

                this.dialog_confirm_command.on('click', 'button[name="confirm"]', () => {
                    this.sendMDMCommand(command, params);
                });
                this.dialog_confirm_command.on('click', 'button[name="close"]', (e) => {
                    $(e.target).closest('.modal').modal('hide');
                });
                this.dialog_confirm_command.modal('show');
            }
        };

        /**
         *
         */
        this.sendMDMCommand = (command, params) => {
            if (params === undefined) {
                params = '';
            }
            $.ajax({
                method: 'POST',
                url: (this.ajax_root + "sendMDMCommand.php"),
                data: {
                    command: command,
                    fields: params,
                    jamf_id: this.jamf_id,
                    itemtype: this.itemtype,
                    items_id: this.items_id
                }
            }).always(function () {
                location.reload();
            });
        };
    };
})();
