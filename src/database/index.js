import { Sequelize } from 'sequelize';
import databaseConfig from '../config/database';
import Tarefa from '../models/Tarefa';

const models = [Tarefa];

const connection = new Sequelize(databaseConfig);

models.forEach((model) => model.init(connection));
