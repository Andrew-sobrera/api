import Sequelize, { Model } from 'sequelize';

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
}
