export default function taskFormatter(userId, task){
    console.log(userId)
    return {
        tarefas: task,
        user_id: userId,
    }
}