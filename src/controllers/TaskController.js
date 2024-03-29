import Tarefa from '../models/Tarefa';
import Tarefa from '../models/Tarefa';

class TaskController {
  async findAll(req, res) {
    const tarefas = await Tarefa.findAll();
    res.json(tarefas);
  }

  async create(req, res) {
    const novaTarefa = await Tarefa.create(req.body);

    res.json(novaTarefa);
  }

  async show(req, res) {
    try {
      const { task } = req.params;

      const findTask = await Tarefa.findOne({
        where: {
          tarefas: task
        }
      });

      if(!findTask){
        return res.status(200).json({
          ok: false
        })
      }
      return res.json({
        Tarefas: findTask,
        ok:true});
    } catch (e) {
     console.log('Erro: ',e)
    }
  }

  async update(req, res) {
    try {
      const { task } = req.params;

      if(!task){
        return res.status(400).json({
          erros: ['tarefa não enviada']
        })
      }

      const findTask = await Tarefa.findOne({
        where: {
          tarefas: task
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
