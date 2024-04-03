import Sequelize, { Model } from 'sequelize';

export default class Tarefa extends Model {
  static init(sequelize) {
    super.init({
      tarefas: {
        type: Sequelize.STRING,
        defaultValue: '',
        validate: {
          len: {
            args: [3, 255],
            msg: 'Campo Nome deve ter entre 3 e 255 caracteres',
          },
        },
      },
      user_id: {
        type: Sequelize.INTEGER
      },
      image_id: {
        type: Sequelize.INTEGER
      }
    }, {
      sequelize,
    });
    return this;
  }
}
