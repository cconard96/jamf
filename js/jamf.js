/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2021 by Curtis Conard
 https://github.com/cconard96/jamf
 -------------------------------------------------------------------------
 LICENSE
 This file is part of JAMF plugin for GLPI.
 JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 JAMF plugin for GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/* global GLPI_PLUGINS_PATH */
/* global CFG_GLPI */
(function () {
   window.JamfPlugin = function () {
      var self = this;

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

      this.init = function (args) {
         if (args !== undefined && args.commands !== undefined) {
            self.commands = args.commands;
            self.jamf_id = args.jamf_id;
            self.itemtype = args.itemtype;
            self.items_id = args.items_id;
            self.ajax_root = args.ajax_root || CFG_GLPI.root_doc + "/" + GLPI_PLUGINS_PATH.jamf + "/ajax/";
         }
      };

      this.onMDMCommandButtonClick = function (command, event) {
         event.preventDefault();
         if (self.commands[command] !== undefined) {
            if (self.commands[command]['params'] !== undefined) {
               showMDMCommandForm(command);
            } else if (self.commands[command]['confirm'] !== undefined && self.commands[command]['confirm'] === true) {
               showMDMCommandConfirmation(command);
            } else {
               self.sendMDMCommand(command);
            }
         }
      };

      /**
       *
       * @param {Object} command
       */
      var showMDMCommandForm = function (command) {
         $.ajax({
            method: 'GET',
            url: (self.ajax_root + "getMDMCommandForm.php"),
            data: {
               command: command,
               jamf_id: self.jamf_id,
               itemtype: self.itemtype,
               items_id: self.items_id
            }
         }).done(function (data) {
            if (data !== undefined && data !== null) {
               if (self.dialog_send_command !== undefined && self.dialog_send_command !== null) {
                  self.dialog_send_command.remove();
               }
               self.dialog_send_command = $(`
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
               self.dialog_send_command.on('click', 'button[name="send"]', () => {
                  if (self.commands[command]['confirm'] !== undefined && self.commands[command]['confirm'] === true) {
                     showMDMCommandConfirmation(command, self.dialog_send_command.serialize());
                  } else {
                     self.sendMDMCommand(command, self.dialog_send_command.serialize());
                  }
               });
               self.dialog_send_command.on('click', 'button[name="close"]', (e) => {
                  $(e.target).closest('.modal').modal('hide');
               });
               self.dialog_send_command.modal('show');
            }
         });
      };

      var showMDMCommandConfirmation = function (command, params) {
         if (self.dialog_confirm_command === undefined || self.dialog_confirm_command === null) {
            const warn_text = _x('message', 'Are you sure you want to send the command: %s?', 'jamf').replace("%s", _x('mdm_command', self.commands[command].name, 'jamf'));

            self.dialog_confirm_command = $(`
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

            self.dialog_confirm_command.on('click', 'button[name="confirm"]', () => {
               self.sendMDMCommand(command, params);
            });
            self.dialog_confirm_command.on('click', 'button[name="close"]', (e) => {
               $(e.target).closest('.modal').modal('hide');
            });
            self.dialog_confirm_command.modal('show');
         }
      };

      /**
       *
       */
      this.sendMDMCommand = function (command, params) {
         if (params === undefined) {
            params = '';
         }
         $.ajax({
            method: 'POST',
            url: (self.ajax_root + "sendMDMCommand.php"),
            data: {
               command: command,
               fields: params,
               jamf_id: self.jamf_id,
               itemtype: self.itemtype,
               items_id: self.items_id
            }
         }).always(function () {
            location.reload();
         });
      };
   };
})();
