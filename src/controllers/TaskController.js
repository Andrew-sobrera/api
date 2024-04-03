import Tarefa from '../models/Tarefa';
import User from '../models/User';
import taskFormatter from '../formatter/tarkFormatter'
import UploaderController from './UploaderController';

class TaskController {
  async findAll(req, res) {

    const user = await User.findOne({
      where : {
        email: req.userEmail
      }
    })
    
    const tarefas = await Tarefa.findAll({
      where: {
        user_id : user.id
      }
    });
    res.json(tarefas);
  }

  async show(req, res) {
    try {
      const { task } = req.params;

      const user = await User.findOne({
        where : {
          email: req.userEmail
        }
      })

      const findTask = await Tarefa.findOne({
        where: {
          tarefas: task,
          user_id: user.id
        }
      });

      if(!findTask){
        return res.status(200).json({
          erro: 'Tarefa não encontrada'
        })
      }
      return res.json({
        Tarefas: findTask,
        ok:true});
    } catch (e) {
     console.log('Erro: ',e)
    }
  }

  async create(req, res) {

    const user = await User.findOne({
      where : {
        email: req.userEmail
      }
    })

    const { tarefas } = req.body
    const novaTarefa = await Tarefa.create(taskFormatter(user.id, tarefas));

    res.json(novaTarefa);
  }

  async update(req, res) {
    try {
      const { task } = req.params;

      const user = await User.findOne({
        where : {
          email: req.userEmail
        }
      })

      if(!task){
        return res.status(400).json({
          erros: ['tarefa não enviada']
        })
      }

      const findTask = await Tarefa.findOne({
        where: {
          tarefas: task,
          user_id: user.id
        }
      });

      if(!findTask){
        return res.status(400).json({
          erros: ['Tarefa não existe']
        })
      }

      const TaskUpdate = await findTask.update(req.body)

      return res.json(TaskUpdate);
    } catch (e) {
      console.log(e)
    }
  }

  async destroy(req,res){
    try {
      const { task } = req.params;

      if(!task){
        return res.status(400).json({
          erros: ['Tarefa não enviada']
        })
      }

      const findTask = await Tarefa.findOne({
        where:{
          tarefas: task
        }
      });

      if(!findTask){
        return res.status(400).json({
          erros: ['Tarefa não existe']
        })
      }

      const taskDestroy = await findTask.destroy()

      return res.status(200).json('success');
    } catch (e) {
      res.status(400).json(
       console.log('erro', e)
      );
    }
  }
}

export default new TaskController();
