import { Sequelize } from 'sequelize';
import databaseConfig from '../config/database';
import Tarefa from '../models/Tarefa';
import User from '../models/User';
import Image from '../models/image';

const models = [Tarefa, User, Image];

const connection = new Sequelize(databaseConfig);

models.forEach((model) => model.init(connection));
