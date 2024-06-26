import express from 'express';
import dotenv from 'dotenv';
import task from './src/routes/taskRoutes';
import user from './src/routes/userRoutes';
import auth from './src/routes/authRoutes';
import uploader from './src/routes/uploaderRoutes';
import cors from 'cors';
import './src/database';
import LoginRequired from './src/middlewares/LoginRequired';
import delay from 'express-delay';

dotenv.config();

const corsOptions = {
  origin: ['http://ec2-54-211-214-149.compute-1.amazonaws.com:3000', 'http://localhost:3000'],
  methods: 'GET,POST,PUT,DELETE', // Especifique os métodos permitidos
  allowedHeaders: ['Content-Type', 'Authorization'], // Especifique os cabeçalhos permitidos
  exposedHeaders: ['Content-Length'], // Especifique os cabeçalhos expostos
};

class App {
  constructor() {
    this.app = express();
    this.middlewares();
    this.routes();
  }
  
  middlewares() {
    this.app.use(cors(corsOptions));
    this.app.use(delay(2000))
    this.app.use(express.urlencoded({ extended: true }));
    this.app.use(express.json());
  }

  routes() {
    this.app.use('/tasks', LoginRequired, task);
    this.app.use('/users', user);
    this.app.use('/uploader', uploader);
    this.app.use('/login', auth);
  }
}

export default new App().app;
