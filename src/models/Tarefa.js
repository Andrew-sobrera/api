import Sequelize, { Model } from 'sequelize';

export default class Tarefa extends Model {
  static init(sequelize) {
    super.init({
      tarefas: Sequelize.STRING,
    }, {
      sequelize,
    });
    return this;
  }
}
