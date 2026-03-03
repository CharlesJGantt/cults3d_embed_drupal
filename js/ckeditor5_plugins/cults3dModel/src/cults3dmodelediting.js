/**
 * @file Defines the editing behavior for the Cults3D Model plugin.
 */

import { Plugin } from 'ckeditor5/src/core';
import { Widget, toWidget } from 'ckeditor5/src/widget';
import InsertCults3dCardCommand from './insertcults3dcardcommand';

export default class Cults3DModelEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this._defineSchema();
    this._defineConverters();

    this.editor.commands.add(
      'insertCults3dCard',
      new InsertCults3dCardCommand(this.editor)
    );
  }

  _defineSchema() {
    const schema = this.editor.model.schema;

    schema.register('cults3dCard', {
      isObject: true,
      allowWhere: '$block',
      isBlock: true,
      allowAttributes: [
        'data-cults3d-url',
        'data-cults3d-name',
        'data-cults3d-desc',
        'data-cults3d-downloads',
        'data-cults3d-price',
        'data-cults3d-thumb',
      ],
    });
  }

  _defineConverters() {
    const { conversion } = this.editor;

    // Upcast: HTML -> Model.
    conversion.for('upcast').elementToElement({
      view: {
        name: 'div',
        classes: ['cults3d-embed-card-wrapper'],
      },
      model: (viewElement, { writer }) => {
        return writer.createElement('cults3dCard', {
          'data-cults3d-url': viewElement.getAttribute('data-cults3d-url'),
          'data-cults3d-name': viewElement.getAttribute('data-cults3d-name'),
          'data-cults3d-desc': viewElement.getAttribute('data-cults3d-desc'),
          'data-cults3d-downloads': viewElement.getAttribute('data-cults3d-downloads'),
          'data-cults3d-price': viewElement.getAttribute('data-cults3d-price'),
          'data-cults3d-thumb': viewElement.getAttribute('data-cults3d-thumb'),
        });
      },
    });

    // Data Downcast: Model -> saved HTML.
    conversion.for('dataDowncast').elementToElement({
      model: 'cults3dCard',
      view: (modelElement, { writer }) => {
        return writer.createContainerElement('div', {
          class: 'cults3d-embed-card-wrapper',
          'data-cults3d-url': modelElement.getAttribute('data-cults3d-url'),
          'data-cults3d-name': modelElement.getAttribute('data-cults3d-name'),
          'data-cults3d-desc': modelElement.getAttribute('data-cults3d-desc'),
          'data-cults3d-downloads': modelElement.getAttribute('data-cults3d-downloads'),
          'data-cults3d-price': modelElement.getAttribute('data-cults3d-price'),
          'data-cults3d-thumb': modelElement.getAttribute('data-cults3d-thumb'),
        });
      },
    });

    // Editing Downcast: Model -> editor display.
    conversion.for('editingDowncast').elementToElement({
      model: 'cults3dCard',
      view: (modelElement, { writer }) => {
        const url = modelElement.getAttribute('data-cults3d-url') || '';
        const name = modelElement.getAttribute('data-cults3d-name') || '';
        const price = modelElement.getAttribute('data-cults3d-price') || 'Free';
        const downloads = modelElement.getAttribute('data-cults3d-downloads') || '0';

        const container = writer.createContainerElement('div', {
          class: 'cults3d-embed-card-wrapper cults3d-embed-card-editor',
        });

        const label = writer.createContainerElement('div', {
          class: 'cults3d-embed-card-editor-label',
        });

        writer.insert(
          writer.createPositionAt(label, 0),
          writer.createText(`Cults3D: ${name} | ${price} | ${downloads} downloads`)
        );

        writer.insert(writer.createPositionAt(container, 0), label);

        return toWidget(container, writer, {
          label: 'Cults3D Model Card',
        });
      },
    });
  }
}
