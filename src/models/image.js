import Sequelize, { Model } from 'sequelize';
import Tarefa from './Tarefa';

export default class Image extends Model {
  static init(sequelize) {
    super.init({
      url: {
        type: Sequelize.STRING,
        defaultValue: '',
      },
    }, {
      sequelize,
    });
    return this;
  }
  
  static associate(models) {
    this.hasMany(models.Tarefa, { foreignKey: 'image_id' });
  }
  
  
}
