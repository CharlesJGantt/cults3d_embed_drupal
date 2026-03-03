/**
 * @file Registers the Cults3D Model toolbar button and binds functionality.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import Cults3DModelEditing from './cults3dmodelediting';
import cults3dIcon from './c3d.svg';

export default class Cults3DModel extends Plugin {
  static get requires() {
    return [Cults3DModelEditing];
  }

  init() {
    const editor = this.editor;

    editor.ui.componentFactory.add('Cults3DModel', (locale) => {
      const command = editor.commands.get('insertCults3dCard');
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: editor.t('Insert Cults3D Model'),
        icon: cults3dIcon,
        tooltip: true,
      });

      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      this.listenTo(buttonView, 'execute', () => {
        editor.execute('insertCults3dCard');
      });

      return buttonView;
    });
  }
}
