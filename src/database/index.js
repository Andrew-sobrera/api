import { Sequelize } from 'sequelize';
import databaseConfig from '../config/database';
import Tarefa from '../models/Tarefa';
import User from '../models/User';

const models = [Tarefa, User];

const connection = new Sequelize(databaseConfig);

models.forEach((model) => model.init(connection));
