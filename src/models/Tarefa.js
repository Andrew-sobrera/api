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
      },
      check: {
        type: Sequelize.BOOLEAN,
        defaultValue: false // Você pode definir um valor padrão se necessário
      }
    }, {
      sequelize,
    });
    return this;
  }

  static associate(models) {
    this.belongsTo(models.Image, { foreignKey: 'image_id' });
  }
}
